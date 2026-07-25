<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\PageModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Pages extends StorefrontController
{
    public function show(string $slug): string
    {
        $page = model(PageModel::class)->findActiveBySlug($slug);

        if ($page === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->page('storefront/page', [
            'page' => $page,
        ], [
            'title'       => ($page['meta_title'] ?: $page['title']) . ' · ' . $this->brand->brandName,
            'description' => $page['meta_description'] ?: rs_excerpt($page['content'], 155),
        ]);
    }
}
