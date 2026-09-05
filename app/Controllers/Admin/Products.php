<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\CategoryModel;
use App\Models\ProductImageModel;
use App\Models\ProductModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Rasmein;

class Products extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('products.view')) {
            return $denied;
        }

        $model = model(ProductModel::class);
        $q     = trim((string) $this->request->getGet('q')) ?: null;
        $cat   = (int) $this->request->getGet('category') ?: null;
        $state = (string) $this->request->getGet('state');

        $model->withPrimaryImage()
            ->select('products.*, categories.name AS category_name', false)
            ->join('categories', 'categories.id = products.category_id', 'left');

        if ($q !== null) {
            $model->groupStart()
                ->like('products.name', $q)->orLike('products.sku', $q)
                ->groupEnd();
        }

        if ($cat !== null) {
            $model->where('products.category_id', $cat);
        }

        if ($state === 'inactive') {
            $model->where('products.is_active', 0);
        } elseif ($state === 'active') {
            $model->where('products.is_active', 1);
        } elseif ($state === 'low') {
            $model->where('products.track_inventory', 1)
                ->where('products.stock_qty <= products.low_stock_threshold', null, false);
        }

        $rows = $model->orderBy('products.id', 'DESC')->paginate(config(Rasmein::class)->adminPerPage);
        $model->pager->only(['q', 'category', 'state']);

        return $this->adminPage('admin/products/index', [
            'products'   => $rows,
            'pager'      => $model->pager,
            'total'      => $model->pager->getTotal(),
            'categories' => model(CategoryModel::class)->activeTopLevel(),
            'filters'    => ['q' => $q, 'category' => $cat, 'state' => $state],
            'canManage'  => $this->can('products.manage'),
        ], 'Products');
    }

    public function create()
    {
        if ($denied = $this->deny('products.manage')) {
            return $denied;
        }

        return $this->form(null);
    }

    public function edit(int $id)
    {
        if ($denied = $this->deny('products.manage')) {
            return $denied;
        }

        $product = model(ProductModel::class)->find($id);

        if ($product === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->form($product);
    }

    public function store()
    {
        if ($denied = $this->deny('products.manage')) {
            return $denied;
        }

        return $this->save(null);
    }

    public function update(int $id)
    {
        if ($denied = $this->deny('products.manage')) {
            return $denied;
        }

        $product = model(ProductModel::class)->find($id);

        if ($product === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($product);
    }

    /** Soft delete — an order's snapshots survive, so history stays intact. */
    public function delete(int $id)
    {
        if ($denied = $this->deny('products.manage')) {
            return $denied;
        }

        $model   = model(ProductModel::class);
        $product = $model->find($id);

        if ($product === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->delete($id);

        service('audit')->log('deleted', 'products', 'product', $id, $product->name . ' (' . $product->sku . ')');

        return redirect()->to(site_url('admin/products'))
            ->with('success', $product->name . ' removed. Past orders keep their record of it.');
    }

    // ------------------------------------------------------------------

    private function form(?object $product): string
    {
        $images = $product !== null
            ? model(ProductImageModel::class)->forProduct((int) $product->id)
            : [];

        // Which values this product already carries, for ticking the boxes.
        // ProductModel returns ENTITIES, not arrays — $product['id'] throws.
        $productId = $product === null ? 0 : (int) $product->id;

        $productValueIds = $productId === 0 ? [] : array_map('intval', array_column(
            db_connect()->table('product_attributes')->select('value_id')
                ->where('product_id', $productId)->get()->getResultArray(),
            'value_id'
        ));

        return $this->adminPage('admin/products/form', [
            'attributes'      => model(\App\Models\AttributeModel::class)->withValues(),
            // Just the count, for the link's badge — the screen itself loads
            // the rows.
            'variantCount'    => $productId === 0 ? 0 : model(\App\Models\ProductVariantModel::class)
                ->where('product_id', $productId)->countAllResults(),
            'productValueIds' => $productValueIds,
            'product'    => $product,
            'images'     => $images,
            'categories' => model(CategoryModel::class)->orderBy('name', 'ASC')->findAll(),
            'maxBytes'   => config(Rasmein::class)->maxImageBytes,
            'occasions'  => model(\App\Models\CollectionModel::class)->occasions(),
            // Existing values, so the datalist suggests what is already in use
            // rather than inviting a new spelling of the same thing.
            'materials'  => array_values(array_filter(array_column(
                db_connect()->table('products')->distinct()->select('material')
                    ->where('material IS NOT NULL', null, false)
                    ->where('material !=', '')->orderBy('material', 'ASC')
                    ->get()->getResultArray(),
                'material'
            ))),
            'taggedOccasions' => $product !== null
                ? model(\App\Models\CollectionModel::class)->occasionIdsForProduct((int) $product->id)
                : [],
            'needsEditor' => true,
        ], $product === null ? 'New product' : 'Edit ' . $product->name);
    }

    private function save(?object $product)
    {
        $isNew = $product === null;
        $model = model(ProductModel::class);
        $id    = $isNew ? null : (int) $product->id;

        $payload = [
            'sku'                 => trim((string) $this->request->getPost('sku')),
            'name'                => trim((string) $this->request->getPost('name')),
            'slug'                => $this->slug((string) $this->request->getPost('slug'), (string) $this->request->getPost('name')),
            'category_id'         => (int) $this->request->getPost('category_id') ?: null,
            'short_description'   => trim((string) $this->request->getPost('short_description')) ?: null,
            'description'         => trim((string) $this->request->getPost('description')) ?: null,
            'price'               => (float) $this->request->getPost('price'),
            'compare_at_price'    => $this->request->getPost('compare_at_price') !== ''
                ? (float) $this->request->getPost('compare_at_price') : null,
            'stock_qty'           => (int) $this->request->getPost('stock_qty'),
            'low_stock_threshold' => (int) $this->request->getPost('low_stock_threshold'),
            'track_inventory'     => $this->request->getPost('track_inventory') !== null ? 1 : 0,
            'unit_label'          => trim((string) $this->request->getPost('unit_label')) ?: null,
            'weight_grams'        => (int) $this->request->getPost('weight_grams') ?: null,
            'sale_mode'           => (string) $this->request->getPost('sale_mode'),
            'material'            => trim((string) $this->request->getPost('material')) ?: null,
            'eyebrow_label'       => trim((string) $this->request->getPost('eyebrow_label')) ?: null,
            'composition'         => trim((string) $this->request->getPost('composition')) ?: null,
            'packaging_note'      => trim((string) $this->request->getPost('packaging_note')) ?: null,
            'care_note'           => trim((string) $this->request->getPost('care_note')) ?: null,
            'personalisation_note' => trim((string) $this->request->getPost('personalisation_note')) ?: null,
            // Blank means "do not show the stars", so an empty string becomes
            // NULL rather than 0 — a 0.0 rating would render five empty stars.
            'rating_average'      => trim((string) $this->request->getPost('rating_average')) !== ''
                ? (float) $this->request->getPost('rating_average')
                : null,
            'review_count'        => (int) $this->request->getPost('review_count'),
            'is_giftbox_eligible' => $this->request->getPost('is_giftbox_eligible') !== null ? 1 : 0,
            'giftbox_slots'       => max(1, (int) $this->request->getPost('giftbox_slots')),
            'is_featured'         => $this->request->getPost('is_featured') !== null ? 1 : 0,
            'is_active'           => $this->request->getPost('is_active') !== null ? 1 : 0,
            'sort_order'          => (int) $this->request->getPost('sort_order'),
            'meta_title'          => trim((string) $this->request->getPost('meta_title')) ?: null,
            'meta_description'    => trim((string) $this->request->getPost('meta_description')) ?: null,
        ];

        // The model owns validation — the same rules apply to a seeder or an
        // import, not just this form.
        //
        // On update the primary key MUST be in the payload: the uniqueness
        // rules are is_unique[products.sku,id,{id}], and CodeIgniter fills
        // {id} from the data it is given, not from update()'s first argument.
        // Without it, editing a product compares its SKU against itself and
        // always fails. `id` is not in $allowedFields, so it is stripped
        // before the write.
        if (! $isNew) {
            $payload['id'] = $id;
        }

        $saved = $isNew ? $model->insert($payload) : $model->update($id, $payload);

        if (! $isNew) {
            unset($payload['id']);
        }

        if ($saved === false) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        if ($isNew) {
            $id = (int) $model->getInsertID();
        }

        // ---- attributes ----
        // Beside the other pivot syncs, and AFTER $id is known — on a new
        // product it does not exist until getInsertID() above.
        $this->syncAttributes($id);

        // ---- occasions ----
        // Done through the model so a product's COLLECTION memberships are left
        // alone: both live in the same pivot, and clearing by product id would
        // silently drop them.
        model(\App\Models\CollectionModel::class)->syncProductOccasions(
            $id,
            array_map('intval', (array) $this->request->getPost('occasions'))
        );

        // ---- alt text on existing images ----
        // Scoped to THIS product's images: an id from the form is not trusted
        // to belong here just because it was posted.
        $alts = (array) $this->request->getPost('image_alt');

        if ($alts !== []) {
            $imageModel = model(ProductImageModel::class);

            foreach ($alts as $imageId => $alt) {
                $imageModel->where('product_id', $id)
                    ->where('id', (int) $imageId)
                    ->set('alt_text', mb_substr(trim((string) $alt), 0, 191) ?: null)
                    ->update();
            }
        }

        // ---- images ----
        $uploadError = $this->handleImages($id);

        service('audit')->log(
            $isNew ? 'created' : 'updated',
            'products',
            'product',
            $id,
            $payload['name'] . ' (' . $payload['sku'] . ')',
            $isNew ? [] : (array) $product->toRawArray(),
            $payload
        );

        $message = $isNew ? 'Product created.' : 'Product saved.';

        return redirect()->to(site_url('admin/products/' . $id . '/edit'))
            ->with($uploadError !== null ? 'error' : 'success', $uploadError ?? $message);
    }

    /** Returns an error message if any upload was refused, else null. */
    private function handleImages(int $productId): ?string
    {
        $files = $this->request->getFileMultiple('images');

        if ($files === null || $files === []) {
            return null;
        }

        $model  = model(ProductImageModel::class);
        $errors = [];
        $notes  = [];
        $added  = 0;

        /*
         * Below this score an image is probably soft. It is a heuristic — a
         * deliberately shallow-focus shot on white scores low too — so it WARNS
         * and never rejects. Telling someone their photograph looks soft at
         * upload time is the only point at which they can do anything about it.
         */
        $softThreshold = 900.0;

        foreach ($files as $file) {
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $result = service('images')->store($file, 'products');

            if (! $result['ok']) {
                $errors[] = $result['error'];

                continue;
            }

            if (($result['sharpness'] ?? null) !== null && $result['sharpness'] < $softThreshold) {
                $notes[] = '“' . $file->getClientName() . '” looks soft. '
                    . 'Sharpening has been applied, but detail that was not captured cannot be recovered — '
                    . 'a higher-resolution or better-focused original will look noticeably better.';
            }

            if (($result['width'] ?? 0) > 0 && $result['width'] < 1000) {
                $notes[] = '“' . $file->getClientName() . '” is only ' . (int) $result['width']
                    . 'px wide. It will look soft on a large screen — 1600px or more is ideal.';
            }

            $existing = $model->where('product_id', $productId)->countAllResults();

            $model->insert([
                'product_id' => $productId,
                'path'       => $result['path'],
                'alt_text'   => null,
                'is_primary' => $existing === 0 ? 1 : 0,
                'sort_order' => $existing + 1,
            ]);

            $added++;
        }

        // Notes are advice, not failures — an upload that worked still reports
        // them, so they are returned alongside rather than instead.
        $advice = $notes === [] ? '' : ' ' . implode(' ', array_unique($notes));

        if ($errors === []) {
            return $advice === '' ? null : trim($advice);
        }

        return ($added > 0 ? $added . ' image(s) added, but: ' : '')
            . implode(' ', array_unique($errors)) . $advice;
    }

    public function deleteImage(int $productId, int $imageId)
    {
        if ($denied = $this->deny('products.manage')) {
            return $denied;
        }

        $model = model(ProductImageModel::class);
        // Scoped to the product, so a guessed image id cannot delete another's.
        $image = $model->where('id', $imageId)->where('product_id', $productId)->first();

        if ($image === null) {
            return redirect()->back()->with('error', 'That image is not on this product.');
        }

        service('images')->delete($image['path']);
        $model->delete($imageId);

        // Promote another image if the primary was the one removed.
        if ((int) $image['is_primary'] === 1) {
            $next = $model->where('product_id', $productId)->orderBy('sort_order', 'ASC')->first();

            if ($next !== null) {
                $model->update($next['id'], ['is_primary' => 1]);
            }
        }

        service('audit')->log('image_removed', 'products', 'product', $productId);

        return redirect()->back()->with('success', 'Image removed.');
    }

    public function makePrimaryImage(int $productId, int $imageId)
    {
        if ($denied = $this->deny('products.manage')) {
            return $denied;
        }

        $model = model(ProductImageModel::class);

        if ($model->where('id', $imageId)->where('product_id', $productId)->first() === null) {
            return redirect()->back()->with('error', 'That image is not on this product.');
        }

        $model->makePrimary($productId, $imageId);

        return redirect()->back()->with('success', 'Main image set.');
    }

    /** Slugify, falling back to the name when the field is left blank. */
    private function slug(string $given, string $name): string
    {
        $source = trim($given) !== '' ? $given : $name;
        $slug   = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $source), '-'));

        return $slug !== '' ? mb_substr($slug, 0, 200) : 'product-' . bin2hex(random_bytes(4));
    }

    /**
     * Replace this product's attribute values with what was ticked.
     *
     * Delete-then-insert rather than a diff: the set is small, the form always
     * posts the COMPLETE selection, and a diff here would be more code for the
     * same result with more ways to leave a stale row behind.
     *
     * Only ids that really exist are written — a hand-edited form must not be
     * able to attach a product to a value that was deleted mid-edit.
     */
    private function syncAttributes(int $productId): void
    {
        $db     = db_connect();
        $posted = array_values(array_unique(array_map(
            'intval',
            (array) $this->request->getPost('attribute_values')
        )));

        $db->table('product_attributes')->where('product_id', $productId)->delete();

        if ($posted === []) {
            return;
        }

        $valid = array_map('intval', array_column(
            $db->table('attribute_values')->select('id')->whereIn('id', $posted)->get()->getResultArray(),
            'id'
        ));

        if ($valid === []) {
            return;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach ($valid as $i => $valueId) {
            $rows[] = [
                'product_id' => $productId,
                'value_id'   => $valueId,
                'sort_order' => $i * 10,
                'created_at' => $now,
            ];
        }

        $db->table('product_attributes')->insertBatch($rows);
    }
}
