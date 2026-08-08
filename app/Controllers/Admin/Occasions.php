<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\CollectionModel;
use App\Models\ProductModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Occasions — Diwali, Raksha Bandhan, weddings, and so on.
 *
 * Backed by the `collections` table with type = 'occasion'; see migration
 * 000015 for why that beats a parallel table. Occasions get an address at the
 * site root, so every slug goes through RootUrlService — the single authority
 * that stops a category and an occasion claiming the same URL.
 */
class Occasions extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $model = model(CollectionModel::class);

        return $this->adminPage('admin/occasions/index', [
            'occasions' => $model->occasions(),
            'counts'    => $model->productCounts(),
        ], 'Occasions');
    }

    public function create()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        return $this->form(null);
    }

    public function edit(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $occasion = model(CollectionModel::class)->find($id);

        if ($occasion === null || ($occasion['type'] ?? '') !== 'occasion') {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->form($occasion);
    }

    public function store()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        return $this->save(null);
    }

    public function update(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $existing = model(CollectionModel::class)->find($id);

        if ($existing === null || ($existing['type'] ?? '') !== 'occasion') {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    public function delete(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $model    = model(CollectionModel::class);
        $occasion = $model->find($id);

        if ($occasion === null || ($occasion['type'] ?? '') !== 'occasion') {
            throw PageNotFoundException::forPageNotFound();
        }

        // The pivot rows go too, or they would point at nothing. Products
        // themselves are untouched — an occasion is a label, not ownership.
        db_connect()->table('collection_products')->where('collection_id', $id)->delete();
        $model->delete($id);

        service('audit')->log('deleted', 'content', 'occasion', $id, $occasion['name']);

        return redirect()->to(site_url('admin/occasions'))
            ->with('success', $occasion['name'] . ' removed. Its products are untouched.');
    }

    // ------------------------------------------------------------------

    private function form(?array $occasion): string
    {
        $model = model(CollectionModel::class);
        $id    = $occasion !== null ? (int) $occasion['id'] : 0;

        return $this->adminPage('admin/occasions/form', [
            'occasion' => $occasion,
            'tagged'   => $id > 0 ? $model->productIds($id) : [],
            'products' => model(ProductModel::class)
                ->select('products.id, products.name, products.sku, products.is_active, categories.name AS category_name')
                ->join('categories', 'categories.id = products.category_id', 'left')
                ->orderBy('categories.name', 'ASC')
                ->orderBy('products.name', 'ASC')
                ->findAll(),
        ], $occasion === null ? 'New occasion' : 'Edit ' . $occasion['name']);
    }

    private function save(?int $id)
    {
        $model = model(CollectionModel::class);
        $name  = trim((string) $this->request->getPost('name'));
        $slug  = trim((string) $this->request->getPost('slug')) ?: $name;
        $slug  = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $slug), '-'));

        if ($name === '' || $slug === '') {
            return redirect()->back()->withInput()->with('error', 'An occasion needs a name that makes a usable web address.');
        }

        // The occasion sits at the site root, so this is the same namespace
        // categories use. One authority answers for both.
        $clash = service('rootUrls')->whyUnavailable($slug, 'occasion', $id);

        if ($clash !== null) {
            return redirect()->back()->withInput()->with('error', $clash . ' Choose a different slug.');
        }

        $starts = trim((string) $this->request->getPost('starts_at')) ?: null;
        $ends   = trim((string) $this->request->getPost('ends_at')) ?: null;

        // A window that closes before it opens can never show the page.
        if ($starts !== null && $ends !== null && strtotime($ends) < strtotime($starts)) {
            return redirect()->back()->withInput()->with('error', 'The end date falls before the start date.');
        }

        $payload = [
            'type'             => 'occasion',
            'name'             => $name,
            'slug'             => $slug,
            'description'      => trim((string) $this->request->getPost('description')) ?: null,
            'starts_at'        => $starts,
            'ends_at'          => $ends,
            'sort_order'       => (int) $this->request->getPost('sort_order'),
            'is_featured'      => $this->request->getPost('is_featured') !== null ? 1 : 0,
            'is_active'        => $this->request->getPost('is_active') !== null ? 1 : 0,
            'meta_title'       => trim((string) $this->request->getPost('meta_title')) ?: null,
            'meta_description' => trim((string) $this->request->getPost('meta_description')) ?: null,
        ];

        $image = $this->request->getFile('image');
        $uploadError = null;

        if ($image !== null && $image->getError() !== UPLOAD_ERR_NO_FILE) {
            $result = service('images')->store($image, 'products');

            $result['ok'] ? $payload['image'] = $result['path'] : $uploadError = $result['error'];
        }

        if ($id !== null) {
            $payload['id'] = $id;
        }

        $saved = $id === null ? $model->insert($payload) : $model->update($id, $payload);

        if ($id !== null) {
            unset($payload['id']);
        }

        if ($saved === false) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $newId  = $id ?? (int) $model->getInsertID();
        $tagged = $model->syncProducts($newId, array_map('intval', (array) $this->request->getPost('products')));

        service('audit')->log(
            $id === null ? 'created' : 'updated',
            'content',
            'occasion',
            $newId,
            $name . ' → /' . $slug . ' (' . $tagged . ' product(s))'
        );

        return redirect()->to(site_url('admin/occasions/' . $newId . '/edit'))
            ->with(
                $uploadError !== null ? 'error' : 'success',
                $uploadError ?? 'Saved. This occasion is at /' . $slug . ' with '
                    . $tagged . ' product' . ($tagged === 1 ? '' : 's') . ' tagged.'
            );
    }
}
