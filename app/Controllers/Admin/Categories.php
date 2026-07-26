<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\CategoryModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Categories extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('products.view')) {
            return $denied;
        }

        return $this->adminPage('admin/categories/index', [
            'categories' => model(CategoryModel::class)->withProductCounts(false),
            'canManage'  => $this->can('categories.manage'),
            'editing'    => null,
        ], 'Categories');
    }

    public function edit(int $id)
    {
        if ($denied = $this->deny('categories.manage')) {
            return $denied;
        }

        $category = model(CategoryModel::class)->find($id);

        if ($category === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->adminPage('admin/categories/index', [
            'categories' => model(CategoryModel::class)->withProductCounts(false),
            'canManage'  => true,
            'editing'    => $category,
        ], 'Edit ' . $category->name);
    }

    public function store()
    {
        if ($denied = $this->deny('categories.manage')) {
            return $denied;
        }

        return $this->save(null);
    }

    public function update(int $id)
    {
        if ($denied = $this->deny('categories.manage')) {
            return $denied;
        }

        if (model(CategoryModel::class)->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    public function delete(int $id)
    {
        if ($denied = $this->deny('categories.manage')) {
            return $denied;
        }

        $model    = model(CategoryModel::class);
        $category = $model->find($id);

        if ($category === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        // Products keep existing with category_id NULL (the FK is SET NULL), so
        // deleting a category never silently destroys stock.
        $model->delete($id);
        service('audit')->log('deleted', 'products', 'category', $id, $category->name);

        return redirect()->to(site_url('admin/categories'))
            ->with('success', $category->name . ' removed. Its products are now uncategorised.');
    }

    private function save(?int $id)
    {
        $model = model(CategoryModel::class);
        $name  = trim((string) $this->request->getPost('name'));
        $slug  = trim((string) $this->request->getPost('slug'));
        $slug  = $slug !== '' ? $slug : $name;

        $payload = [
            'name'             => $name,
            'slug'             => strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $slug), '-')),
            'description'      => trim((string) $this->request->getPost('description')) ?: null,
            'parent_id'        => (int) $this->request->getPost('parent_id') ?: null,
            'sort_order'       => (int) $this->request->getPost('sort_order'),
            'is_featured'      => $this->request->getPost('is_featured') !== null ? 1 : 0,
            'is_active'        => $this->request->getPost('is_active') !== null ? 1 : 0,
            'meta_title'       => trim((string) $this->request->getPost('meta_title')) ?: null,
            'meta_description' => trim((string) $this->request->getPost('meta_description')) ?: null,
        ];

        // A category cannot be its own parent.
        if ($id !== null && $payload['parent_id'] === $id) {
            $payload['parent_id'] = null;
        }

        $image = $this->request->getFile('image');
        $uploadError = null;

        if ($image !== null && $image->getError() !== UPLOAD_ERR_NO_FILE) {
            $result = service('images')->store($image, 'products');

            if ($result['ok']) {
                $payload['image'] = $result['path'];
            } else {
                $uploadError = $result['error'];
            }
        }

        // See the note in Products::save() — {id} is filled from the payload,
        // so without this an edit fails its own uniqueness check.
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

        $newId = $id ?? (int) $model->getInsertID();
        service('audit')->log($id === null ? 'created' : 'updated', 'products', 'category', $newId, $payload['name']);

        return redirect()->to(site_url('admin/categories'))
            ->with($uploadError !== null ? 'error' : 'success', $uploadError ?? 'Category saved.');
    }
}
