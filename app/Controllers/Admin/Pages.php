<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\PageModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * CMS pages.
 *
 * Content is HTML, and the storefront renders it unescaped — which is only safe
 * because PageModel sanitises on save through an allowlist. That callback is the
 * thing that makes this editor shippable; do not bypass the model to write
 * pages.content directly.
 */
class Pages extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        return $this->adminPage('admin/pages/index', [
            'pages' => model(PageModel::class)->orderBy('sort_order', 'ASC')->findAll(),
        ], 'Pages');
    }

    public function create()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        return $this->adminPage('admin/pages/form', ['page' => null], 'New page');
    }

    public function edit(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $page = model(PageModel::class)->find($id);

        if ($page === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->adminPage('admin/pages/form', ['page' => $page], 'Edit ' . $page['title']);
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

        if (model(PageModel::class)->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    public function delete(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $model = model(PageModel::class);
        $page  = $model->find($id);

        if ($page === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->delete($id);
        service('audit')->log('deleted', 'content', 'page', $id, $page['title']);

        return redirect()->to(site_url('admin/pages'))->with('success', $page['title'] . ' removed.');
    }

    private function save(?int $id)
    {
        $model = model(PageModel::class);
        $title = trim((string) $this->request->getPost('title'));
        $slug  = trim((string) $this->request->getPost('slug')) ?: $title;
        $raw   = (string) $this->request->getPost('content');

        $payload = [
            'title'            => $title,
            'slug'             => strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $slug), '-')),
            'excerpt'          => trim((string) $this->request->getPost('excerpt')) ?: null,
            // Passed raw: PageModel's beforeInsert/beforeUpdate callback runs it
            // through the allowlist sanitiser. Sanitising here as well would
            // mean two places to keep in step.
            'content'          => $raw,
            'show_in_footer'   => $this->request->getPost('show_in_footer') !== null ? 1 : 0,
            'sort_order'       => (int) $this->request->getPost('sort_order'),
            'is_active'        => $this->request->getPost('is_active') !== null ? 1 : 0,
            'meta_title'       => trim((string) $this->request->getPost('meta_title')) ?: null,
            'meta_description' => trim((string) $this->request->getPost('meta_description')) ?: null,
        ];

        if ($id !== null) {
            $payload['id'] = $id;
        }

        $saved = $id === null ? $model->insert($payload) : $model->update($id, $payload);

        if ($saved === false) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $newId = $id ?? (int) $model->getInsertID();
        $stored = $model->find($newId);

        // If the sanitiser removed something, say so plainly rather than letting
        // the author wonder where their markup went.
        $stripped = $stored !== null && $raw !== '' && strlen((string) $stored['content']) < strlen($raw) * 0.9;

        service('audit')->log($id === null ? 'created' : 'updated', 'content', 'page', $newId, $payload['title']);

        return redirect()->to(site_url('admin/pages/' . $newId . '/edit'))
            ->with('success', 'Page saved.' . ($stripped ? ' Some markup was removed — only basic formatting is allowed.' : ''));
    }
}
