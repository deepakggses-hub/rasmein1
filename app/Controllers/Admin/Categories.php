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
            'categories' => model(CategoryModel::class)->tree(),
            'counts'     => $this->productCounts(),
            'canManage'  => $this->can('categories.manage'),
            'editing'    => null,
            'maxDepth'   => CategoryModel::MAX_DEPTH,
        ], 'Categories');
    }

    /** Products directly in each category. @return array<int, int> */
    private function productCounts(): array
    {
        $out = [];

        foreach (db_connect()->table('products')
            ->select('category_id, COUNT(*) AS n', false)
            ->where('deleted_at', null)
            ->where('category_id IS NOT NULL', null, false)
            ->groupBy('category_id')->get()->getResultArray() as $row) {
            $out[(int) $row['category_id']] = (int) $row['n'];
        }

        return $out;
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
            'categories' => model(CategoryModel::class)->tree(),
            'counts'     => $this->productCounts(),
            'canManage'  => true,
            'editing'    => $category,
            'maxDepth'   => CategoryModel::MAX_DEPTH,
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

        // A category with children cannot go: the foreign key would orphan them
        // and their paths would point at nothing. Say so rather than cascading,
        // which would silently delete a whole branch.
        $children = $model->where('parent_id', $id)->countAllResults();

        if ($children > 0) {
            return redirect()->back()->with(
                'error',
                $category->name . ' has ' . $children . ' subcategor'
                    . ($children === 1 ? 'y' : 'ies') . '. Move or remove those first.'
            );
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
        $slug  = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $slug), '-'));

        $parentId = (int) $this->request->getPost('parent_id') ?: null;

        // ---- the guards, in the order a person would hit them ----

        if ($slug === '') {
            return redirect()->back()->withInput()->with('error', 'That name does not produce a usable URL. Set a slug by hand.');
        }

        // A category can not be its own ancestor. Without this a stray
        // selection detaches a whole branch from the root, and every path
        // beneath it becomes unreachable.
        if ($id !== null && $model->wouldCycle($id, $parentId)) {
            return redirect()->back()->withInput()->with(
                'error',
                'A category cannot sit inside itself or one of its own subcategories.'
            );
        }

        if ($parentId !== null && $model->find($parentId) === null) {
            return redirect()->back()->withInput()->with('error', 'That parent category no longer exists.');
        }

        $depth = $model->depthUnder($parentId);

        if ($depth > CategoryModel::MAX_DEPTH) {
            return redirect()->back()->withInput()->with(
                'error',
                'That would nest ' . ($depth + 1) . ' levels deep. The limit is '
                    . (CategoryModel::MAX_DEPTH + 1) . ' — deeper URLs stop being useful to anyone.'
            );
        }

        // A TOP-LEVEL slug becomes a root URL, so it must not shadow a route,
        // another category, or an occasion. Nested slugs are safe: they only
        // ever appear after a parent segment.
        if ($parentId === null) {
            $clash = service('rootUrls')->whyUnavailable($slug, 'category', $id);

            if ($clash !== null) {
                return redirect()->back()->withInput()->with(
                    'error',
                    $clash . ' Choose a different slug, or put this category inside another one.'
                );
            }
        }

        $path = $model->buildPath($parentId, $slug);

        // Two categories cannot occupy the same URL. The database enforces it
        // too, but a message beats a constraint violation.
        $clash = $model->where('path', $path);

        if ($id !== null) {
            $clash->where('id !=', $id);
        }

        if ($clash->countAllResults() > 0) {
            return redirect()->back()->withInput()->with(
                'error',
                'Another category already lives at /' . $path . '. Change the slug.'
            );
        }

        // Captured BEFORE the write: rebuildSubtree needs to know where this
        // category used to live in order to move its descendants with it.
        $previousPath = null;

        if ($id !== null) {
            $before = $model->select('id, path')->where('id', $id)->first();
            $previousPath = $before !== null ? (string) ($before->path ?? '') : null;
        }

        $payload = [
            'name'             => $name,
            'slug'             => $slug,
            'path'             => $path,
            'depth'            => $depth,
            'parent_id'        => $parentId,
            'description'      => trim((string) $this->request->getPost('description')) ?: null,
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

        // See CLAUDE.md — {id} is filled from the payload, not update()'s first
        // argument.
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

        // Renaming or reparenting moves every descendant's URL with it.
        $touched = $model->rebuildSubtree($newId, $previousPath);

        service('audit')->log(
            $id === null ? 'created' : 'updated',
            'products',
            'category',
            $newId,
            $payload['name'] . ' → /' . $path
        );

        $note = $touched > 1
            ? ' ' . ($touched - 1) . ' subcategor' . ($touched === 2 ? 'y' : 'ies') . ' moved with it.'
            : '';

        return redirect()->to(site_url('admin/categories'))
            ->with(
                $uploadError !== null ? 'error' : 'success',
                $uploadError ?? 'Saved. This category is at /' . $path . '.' . $note
            );
    }
}
