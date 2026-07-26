<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Image upload from inside the rich text editor.
 *
 * Goes through the same ImageUploadService as every other upload — the type is
 * decided by reading the file, the image is re-encoded through GD (which
 * destroys polyglot payloads and strips EXIF), the filename is generated, and
 * the destination is chosen by key rather than by anything posted.
 *
 * Returns JSON because the editor is calling it with fetch(), and returns a
 * fresh CSRF hash because CodeIgniter rotates the token per request — without
 * that, a second upload in the same session would be rejected.
 */
class EditorUpload extends AdminController
{
    public function store()
    {
        if (! $this->can('content.manage') && ! $this->can('products.manage')) {
            return $this->response->setStatusCode(403)->setJSON([
                'ok'    => false,
                'error' => 'You do not have permission to upload images.',
            ]);
        }

        // If the request reached here at all, CSRF passed — the framework
        // rejects it earlier otherwise. Worth stating, because a 403 on this
        // endpoint is nearly always CSRF or a cross-origin baseURL, not roles.
        $file = $this->request->getFile('image');

        if ($file === null) {
            return $this->response->setJSON(['ok' => false, 'error' => 'No file was received.']);
        }

        // Rate limited: an editor upload button is an easy way to fill a disk.
        if (service('throttler')->check('editor_upload_' . (int) session('admin_id'), 60, HOUR) === false) {
            return $this->response->setStatusCode(429)->setJSON([
                'ok'    => false,
                'error' => 'Too many uploads in the last hour. Try again shortly.',
            ]);
        }

        $result = service('images')->store($file, 'content');

        if (! $result['ok']) {
            return $this->response->setJSON([
                'ok'    => false,
                'error' => $result['error'],
                'csrf'  => csrf_hash(),
            ]);
        }

        service('audit')->log('image_uploaded', 'content', 'editor_image', null, $result['path']);

        // A ROOT-RELATIVE src, not base_url(). base_url() builds an absolute
        // URL from app.baseURL; if that is misconfigured every image inserted
        // through the editor points at the wrong host and renders broken —
        // including on the live storefront, long after the upload succeeded.
        // A relative src resolves against whatever host is serving the page.
        return $this->response->setJSON([
            'ok'   => true,
            'url'  => '/' . ltrim($result['path'], '/'),
            'csrf' => csrf_hash(),
        ]);
    }
}
