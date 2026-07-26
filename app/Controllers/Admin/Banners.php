<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\BannerModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Banners extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        return $this->adminPage('admin/banners/index', [
            'banners' => model(BannerModel::class)
                ->orderBy('position', 'ASC')->orderBy('sort_order', 'ASC')->findAll(),
        ], 'Banners');
    }

    public function create()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        return $this->adminPage('admin/banners/form', ['banner' => null], 'New banner');
    }

    public function edit(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $banner = model(BannerModel::class)->find($id);

        if ($banner === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->adminPage('admin/banners/form', ['banner' => $banner], 'Edit banner');
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

        if (model(BannerModel::class)->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    public function delete(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $model  = model(BannerModel::class);
        $banner = $model->find($id);

        if ($banner === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        service('images')->delete($banner['image']);
        $model->delete($id);
        service('audit')->log('deleted', 'content', 'banner', $id, $banner['title'] ?? 'banner');

        return redirect()->to(site_url('admin/banners'))->with('success', 'Banner removed.');
    }

    private function save(?int $id)
    {
        $model = model(BannerModel::class);

        $payload = [
            'eyebrow'    => trim((string) $this->request->getPost('eyebrow')) ?: null,
            'title'      => trim((string) $this->request->getPost('title')) ?: null,
            'subtitle'   => trim((string) $this->request->getPost('subtitle')) ?: null,
            'alt_text'   => trim((string) $this->request->getPost('alt_text')) ?: null,
            'cta_label'  => trim((string) $this->request->getPost('cta_label')) ?: null,
            'position'   => (string) $this->request->getPost('position'),
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'starts_at'  => $this->request->getPost('starts_at') ?: null,
            'ends_at'    => $this->request->getPost('ends_at') ?: null,
            'is_active'  => $this->request->getPost('is_active') !== null ? 1 : 0,
        ];

        // A banner link must stay on this site — an admin-set off-site redirect
        // on the homepage hero is exactly what an attacker with a stolen staff
        // password would reach for.
        $link = trim((string) $this->request->getPost('link_url'));

        if ($link !== '' && ! str_starts_with($link, '/') && ! str_starts_with($link, site_url())) {
            return redirect()->back()->withInput()->with(
                'error',
                'Banner links must point somewhere on this site — start with / or the site address.'
            );
        }

        $payload['link_url'] = $link ?: null;

        if ($payload['starts_at'] !== null && $payload['ends_at'] !== null
            && strtotime((string) $payload['ends_at']) < strtotime((string) $payload['starts_at'])) {
            return redirect()->back()->withInput()->with('error', 'The end date falls before the start date.');
        }

        $image = $this->request->getFile('image');
        $uploadError = null;

        if ($image !== null && $image->getError() !== UPLOAD_ERR_NO_FILE) {
            $result = service('images')->store($image, 'banners');

            $result['ok'] ? $payload['image'] = $result['path'] : $uploadError = $result['error'];
        }

        if ($id !== null) {
            $payload['id'] = $id;
        }

        $saved = $id === null ? $model->insert($payload) : $model->update($id, $payload);

        if ($saved === false) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $newId = $id ?? (int) $model->getInsertID();
        service('audit')->log($id === null ? 'created' : 'updated', 'content', 'banner', $newId, $payload['title'] ?? 'banner');

        return redirect()->to(site_url('admin/banners/' . $newId . '/edit'))
            ->with($uploadError !== null ? 'error' : 'success', $uploadError ?? 'Banner saved.');
    }
}
