<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\MediaModel;

/**
 * The media library the picker talks to.
 *
 * JSON only: the picker is a modal that any file input can open, so it needs
 * data rather than a page. The browsing screen itself is the modal.
 */
class MediaLibrary extends AdminController
{
    /** What is already uploaded. */
    public function browse()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $result = model(MediaModel::class)->browse(
            (string) $this->request->getGet('q'),
            (string) $this->request->getGet('collection'),
            max(1, (int) $this->request->getGet('page')),
        );

        return $this->response->setJSON([
            'ok'    => true,
            'total' => $result['total'],
            'items' => array_map(static fn (array $row): array => [
                'id'    => (int) $row['id'],
                'path'  => $row['path'],
                // Both sizes: the grid wants a thumbnail, the chosen field
                // wants the real path to store.
                'thumb' => rs_image($row['path'], 'thumb'),
                'name'  => $row['filename'],
                'alt'   => $row['alt_text'] ?? '',
                'size'  => $row['width'] !== null ? $row['width'] . ' × ' . $row['height'] : '',
            ], $result['items']),
        ]);
    }

    /** Add a file from the machine, and return it ready to select. */
    public function upload()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $file = $this->request->getFile('file');

        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->response->setStatusCode(422)
                ->setJSON(['ok' => false, 'error' => 'No file was sent.']);
        }

        $collection = (string) ($this->request->getPost('collection') ?: 'content');

        // The same uploader every other screen uses: it validates the type,
        // re-encodes the image and builds the size variants. A second upload
        // path would be a second set of rules to keep in step.
        $result = service('images')->store($file, $collection);

        if (! $result['ok']) {
            return $this->response->setStatusCode(422)
                ->setJSON(['ok' => false, 'error' => $result['error']]);
        }

        $row = model(MediaModel::class)->remember(
            $result['path'],
            $file->getClientName(),
            $collection
        );

        service('audit')->log('created', 'content', 'media', (int) ($row['id'] ?? 0), $row['filename'] ?? '');

        return $this->response->setJSON([
            'ok'   => true,
            'item' => [
                'id'    => (int) ($row['id'] ?? 0),
                'path'  => $row['path'] ?? $result['path'],
                'thumb' => rs_image($row['path'] ?? $result['path'], 'thumb'),
                'name'  => $row['filename'] ?? '',
                'alt'   => $row['alt_text'] ?? '',
                'size'  => ($row['width'] ?? null) !== null ? $row['width'] . ' × ' . $row['height'] : '',
            ],
        ]);
    }

    /** Rename what a picture SHOWS, from inside the picker. */
    public function alt(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $model = model(MediaModel::class);

        if ($model->find($id) === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false]);
        }

        $model->update($id, [
            'id'       => $id,
            'alt_text' => mb_substr(trim((string) $this->request->getPost('alt_text')), 0, 191) ?: null,
        ]);

        return $this->response->setJSON(['ok' => true]);
    }

    /** The browsing screen — a page, not the modal. */
    public function index()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $model = model(MediaModel::class);
        $page  = max(1, (int) $this->request->getGet('page'));

        $result = $model->browse(
            (string) $this->request->getGet('q'),
            (string) $this->request->getGet('collection'),
            $page,
            48
        );

        return $this->adminPage('admin/media/library', [
            'items'      => $result['items'],
            'total'      => $result['total'],
            'page'       => $page,
            'perPage'    => 48,
            'term'       => (string) $this->request->getGet('q'),
            'collection' => (string) $this->request->getGet('collection'),
            // Only folders that actually hold something, so the filter never
            // offers an empty view.
            'folders'    => array_column(
                db_connect()->table('media')->select('collection')
                    ->distinct()->orderBy('collection', 'ASC')->get()->getResultArray(),
                'collection'
            ),
        ], 'Media library');
    }

    /**
     * Remove a picture.
     *
     * The FILE goes too, and its generated variants — a library row pointing at
     * nothing is worse than no row, because the picker would offer it.
     */
    public function delete(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $model = model(MediaModel::class);
        $row   = $model->find($id);

        if ($row === null) {
            return redirect()->to(site_url('admin/media-library'))->with('error', 'That picture is already gone.');
        }

        /*
         * Refuse while it is still in use.
         *
         * Deleting a picture a product is showing leaves a broken image on the
         * storefront, and the shop has no way to tell which product broke.
         */
        $used = $this->inUse((string) $row['path']);

        if ($used !== []) {
            return redirect()->to(site_url('admin/media-library'))->with(
                'error',
                'That picture is still used by ' . implode(', ', $used) . '. Replace it there first.'
            );
        }

        service('images')->delete((string) $row['path']);
        $model->delete($id);
        service('audit')->log('deleted', 'content', 'media', $id, (string) $row['filename']);

        return redirect()->to(site_url('admin/media-library'))->with('success', 'Picture deleted.');
    }

    /**
     * Where a path is still referenced.
     *
     * @return list<string>
     */
    private function inUse(string $path): array
    {
        $db  = db_connect();
        $out = [];

        foreach ([
            ['product_images', 'path', 'a product'],
            ['categories', 'image', 'a category'],
            ['collections', 'image', 'a collection'],
            ['banners', 'image', 'a banner'],
            ['banners', 'mobile_image', 'a banner'],
            ['gift_boxes', 'image', 'a gift box'],
            ['testimonials', 'image', 'a testimonial'],
            ['product_variants', 'image', 'a variant'],
        ] as [$table, $column, $label]) {
            if (! $db->tableExists($table) || ! $db->fieldExists($column, $table)) {
                continue;
            }

            if ($db->table($table)->where($column, $path)->countAllResults() > 0) {
                $out[$label] = $label;
            }
        }

        return array_values($out);
    }

    /**
     * Delete several at once.
     *
     * Each is checked for use independently — one picture still on a product
     * must not stop the other nine from going, and the reply says exactly which
     * were kept and why.
     */
    public function bulkDelete()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) $this->request->getPost('ids')
        ))));

        if ($ids === []) {
            return redirect()->to(site_url('admin/media-library'))
                ->with('error', 'Nothing was selected.');
        }

        $model   = model(MediaModel::class);
        $deleted = 0;
        $kept    = [];

        foreach ($model->whereIn('id', $ids)->findAll() as $row) {
            $used = $this->inUse((string) $row['path']);

            if ($used !== []) {
                $kept[] = (string) $row['filename'];

                continue;
            }

            service('images')->delete((string) $row['path']);
            $model->delete((int) $row['id']);
            $deleted++;
        }

        if ($deleted > 0) {
            service('audit')->log('deleted', 'content', 'media', null, $deleted . ' picture(s)');
        }

        $message = $deleted . ($deleted === 1 ? ' picture deleted.' : ' pictures deleted.');

        if ($kept !== []) {
            // Named, because "some were skipped" leaves the shop hunting.
            $message .= ' Kept ' . count($kept) . ' still in use: ' . implode(', ', array_slice($kept, 0, 5))
                . (count($kept) > 5 ? ' and others.' : '.');
        }

        return redirect()->to(site_url('admin/media-library'))
            ->with($kept === [] ? 'success' : 'error', $message);
    }
}
