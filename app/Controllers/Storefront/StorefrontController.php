<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\PageModel;

/**
 * Base for public-facing pages. Assembles the chrome (nav, footer, journey
 * mode) once so individual pages only supply their own content.
 */
abstract class StorefrontController extends BaseController
{
    /**
     * Render a storefront page inside the shared layout.
     *
     * @param array<string, mixed> $data Page data
     * @param array<string, mixed> $seo  title | description | canonical | image | noindex
     */
    protected function page(string $view, array $data = [], array $seo = []): string
    {
        $mode = $this->settings->journeyMode();

        $chrome = [
            'brand'        => $this->brand,
            'journeyMode'  => $mode,
            'isEnquire'    => $mode === \Config\Rasmein::MODE_ENQUIRE,
            'navCategories' => model(CategoryModel::class)->activeTopLevel(),
            'footerPages'  => model(PageModel::class)->footerLinks(),
            'seo'          => array_merge([
                'title'       => $this->brand->brandName,
                'description' => $this->brand->brandTagline,
                'canonical'   => current_url(),
                'image'       => null,
                'noindex'     => false,
            ], $seo),
        ];

        return view($view, array_merge($chrome, $data));
    }
}
