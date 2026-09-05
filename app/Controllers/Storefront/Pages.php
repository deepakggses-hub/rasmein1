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

        /*
         * Which layout to render comes from the page itself. An unknown or
         * missing template falls back to the standard one rather than 500ing —
         * a template can be removed from the config while a page still names it.
         */
        $template = config(\Config\PageTemplates::class)->get((string) ($page['template'] ?? 'standard'));

        $decoded = $page['data'] !== null && $page['data'] !== ''
            ? json_decode((string) $page['data'], true)
            : [];

        return $this->page($template['view'], [
            'page' => $page,
            'data' => is_array($decoded) ? $decoded : [],
        ], [
            'title'       => ($page['meta_title'] ?: $page['title']) . ' · ' . $this->brand->brandName,
            'description' => $page['meta_description'] ?: rs_excerpt($page['content'], 155),
        ]);
    }
}
