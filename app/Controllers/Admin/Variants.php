<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AttributeModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;

/**
 * Variants for one product.
 *
 * A screen of its own rather than another block on the product form. A piece
 * can have a dozen combinations, each with its own price, stock, picture and
 * description — folding that into a form that already covers copy, SEO, images
 * and attributes would bury both.
 */
class Variants extends AdminController
{
    public function index(int $productId)
    {
        if ($denied = $this->deny('catalogue.manage')) {
            return $denied;
        }

        $product = model(ProductModel::class)->find($productId);

        if ($product === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $model  = model(ProductVariantModel::class);
        $matrix = $model->matrixFor($productId);

        return $this->adminPage('admin/variants/index', [
            'product'  => $product,
            'variants' => $matrix['variants'],
            'groups'   => $matrix['groups'],
            // Only the attributes the shop said a customer chooses between —
            // a size is a fact about the piece, not a combination to stock.
            'choosable' => $this->choosableValues($productId),
            'inCarts'   => $this->inCarts($productId),
        ], 'Variants · ' . $product->name);
    }

    /** Update the rows that were edited, in one pass. */
    public function save(int $productId)
    {
        if ($denied = $this->deny('catalogue.manage')) {
            return $denied;
        }

        $model   = model(ProductVariantModel::class);
        $posted  = (array) $this->request->getPost('variant');
        $changed = 0;

        foreach ($posted as $id => $row) {
            $id      = (int) $id;
            $variant = $model->find($id);

            // A posted id must belong to THIS product. Without the check, a
            // hand-edited form could reprice someone else's variant.
            if ($variant === null || (int) $variant['product_id'] !== $productId) {
                continue;
            }

            /*
             * Blank means "follow the product", not zero.
             *
             * That distinction is the whole point of the nullable columns: a
             * cleared price field must return the variant to the product's
             * price, not set it to free.
             */
            $price   = trim((string) ($row['price'] ?? ''));
            $compare = trim((string) ($row['compare_at_price'] ?? ''));

            $payload = [
                'id'               => $id,
                'label'            => trim((string) ($row['label'] ?? $variant['label'])) ?: $variant['label'],
                'price'            => $price === '' ? null : (float) $price,
                'compare_at_price' => $compare === '' ? null : (float) $compare,
                'description'      => trim((string) ($row['description'] ?? '')) ?: null,
                'stock_qty'        => max(0, (int) ($row['stock_qty'] ?? 0)),
                'is_active'        => isset($row['is_active']) ? 1 : 0,
                'sort_order'       => (int) ($row['sort_order'] ?? 0),
            ];

            $image = $this->request->getFile('image_' . $id);

            if ($image !== null && $image->getError() !== UPLOAD_ERR_NO_FILE) {
                $result = service('images')->store($image, 'content');

                // A failed upload keeps the old picture rather than clearing
                // it — losing an image because a file was too large is not
                // what anyone meant.
                if ($result['ok']) {
                    $payload['image'] = $result['path'];
                }
            }

            if ($model->update($id, $payload)) {
                $changed++;
            }
        }

        // Exactly one default, always. Two would make pick() arbitrary; none
        // would make a product open on whatever sorted first.
        $default = (int) $this->request->getPost('is_default');

        if ($default > 0) {
            $db = db_connect();
            $db->table('product_variants')->where('product_id', $productId)->update(['is_default' => 0]);
            $db->table('product_variants')->where('id', $default)->where('product_id', $productId)
                ->update(['is_default' => 1]);
        }

        service('audit')->log('updated', 'catalogue', 'variant', $productId, $changed . ' variant(s)');

        return redirect()->back()->with('success', $changed . ' variant' . ($changed === 1 ? '' : 's') . ' saved.');
    }

    /** Add a combination the product does not yet have. */
    public function create(int $productId)
    {
        if ($denied = $this->deny('catalogue.manage')) {
            return $denied;
        }

        $product = model(ProductModel::class)->find($productId);

        if ($product === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $valueIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) $this->request->getPost('values')
        ))));

        if ($valueIds === []) {
            return redirect()->back()->with('error', 'Choose at least one value for the new variant.');
        }

        $db     = db_connect();
        $labels = array_column(
            $db->table('attribute_values')->select('label')->whereIn('id', $valueIds)
                ->orderBy('sort_order', 'ASC')->get()->getResultArray(),
            'label'
        );

        $key = $this->slug(implode('-', $labels));

        // The same combination twice would make selection ambiguous — two
        // variants would match one set of choices.
        $clash = model(ProductVariantModel::class)->byKey($productId, $key);

        if ($clash !== null) {
            return redirect()->back()->with('error', 'That combination already exists.');
        }

        $model = model(ProductVariantModel::class);

        $model->insert([
            'product_id'  => $productId,
            'sku'         => $product->sku . '-' . strtoupper(substr($key, 0, 12)) . '-' . (time() % 10000),
            'variant_key' => $key,
            'label'       => implode(' · ', $labels),
            'stock_qty'   => 0,
            'is_active'   => 1,
            'sort_order'  => 900,
        ]);

        $variantId = (int) $model->getInsertID();

        foreach ($valueIds as $valueId) {
            $db->table('variant_values')->insert(['variant_id' => $variantId, 'value_id' => $valueId]);
        }

        return redirect()->back()->with('success', 'Variant added. Set its price and stock below.');
    }

    public function delete(int $productId, int $id)
    {
        if ($denied = $this->deny('catalogue.manage')) {
            return $denied;
        }

        $variant = model(ProductVariantModel::class)->find($id);

        if ($variant === null || (int) $variant['product_id'] !== $productId) {
            return redirect()->back();
        }

        $used = db_connect()->table('cart_items')->where('variant_id', $id)->countAllResults();

        if ($used > 0) {
            /*
             * Refused while it is in someone's basket. Deleting would cascade
             * the line away mid-shop, and the customer would simply find their
             * basket lighter with nothing explaining why. Deactivating hides it
             * from the storefront and leaves the basket intact.
             */
            return redirect()->back()->with(
                'error',
                'That variant is in ' . $used . ' basket' . ($used === 1 ? '' : 's') . '. Switch it off instead.'
            );
        }

        model(ProductVariantModel::class)->delete($id);
        service('audit')->log('deleted', 'catalogue', 'variant', $id, (string) $variant['label']);

        return redirect()->back()->with('success', 'Variant removed.');
    }

    // -----------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function choosableValues(int $productId): array
    {
        $rows = db_connect()->table('product_attributes pa')
            ->select('av.id, av.label, av.swatch_hex, a.name AS attribute, a.code', false)
            ->join('attribute_values av', 'av.id = pa.value_id')
            ->join('attributes a', 'a.id = av.attribute_id')
            ->where('pa.product_id', $productId)
            ->where('a.is_selectable', 1)
            ->orderBy('a.sort_order', 'ASC')->orderBy('av.sort_order', 'ASC')
            ->get()->getResultArray();

        $out = [];

        foreach ($rows as $row) {
            $out[(string) $row['attribute']][] = $row;
        }

        return $out;
    }

    /** @return array<int, int> */
    private function inCarts(int $productId): array
    {
        $out = [];

        foreach (db_connect()->table('cart_items ci')
            ->select('ci.variant_id, COUNT(*) AS n', false)
            ->join('product_variants v', 'v.id = ci.variant_id')
            ->where('v.product_id', $productId)
            ->groupBy('ci.variant_id')->get()->getResultArray() as $row) {
            $out[(int) $row['variant_id']] = (int) $row['n'];
        }

        return $out;
    }

    private function slug(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/["\x27]/', '', $s) ?? $s;
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? $s;

        return trim($s, '-') ?: 'variant';
    }
}
