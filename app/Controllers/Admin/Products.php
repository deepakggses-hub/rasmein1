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

        return $this->adminPage('admin/products/form', [
            'product'    => $product,
            'images'     => $images,
            'categories' => model(CategoryModel::class)->orderBy('name', 'ASC')->findAll(),
            'maxBytes'   => config(Rasmein::class)->maxImageBytes,
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
        $added  = 0;

        foreach ($files as $file) {
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $result = service('images')->store($file, 'products');

            if (! $result['ok']) {
                $errors[] = $result['error'];

                continue;
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

        if ($errors === []) {
            return null;
        }

        return ($added > 0 ? $added . ' image(s) added, but: ' : '') . implode(' ', array_unique($errors));
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
}
