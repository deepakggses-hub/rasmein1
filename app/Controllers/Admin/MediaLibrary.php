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
}
