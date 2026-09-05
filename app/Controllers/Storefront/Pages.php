<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\PageModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Pages extends StorefrontController
{
    public function show(string $slug)
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

        /*
         * A template with its own route is rendered THERE, permanently.
         *
         * Some templates need data this generic action has no way to build —
         * the collections page needs occasion tiles and their products. Without
         * this, /page/collections rendered the same layout with those sections
         * silently empty, and the shop had two addresses for one page with only
         * one of them working.
         */
        if (! empty($template['route'])) {
            return redirect()->to(site_url($template['route']), 301);
        }

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

    /**
     * The collections landing page at /collection.
     *
     * A page row with the `collections` template, so the whole existing Pages
     * editor drives it — a bespoke admin screen would be a second thing to
     * maintain for the same six sections.
     */
    public function collections()
    {
        $page = model(\App\Models\PageModel::class)
            ->where('template', 'collections')
            ->where('is_active', 1)
            ->first();

        if ($page === null) {
            // No page configured yet: send them to the plain index rather than
            // 404 a link that sits in the main navigation.
            return redirect()->to(site_url('collections'));
        }

        $decoded = ! empty($page['data']) ? json_decode((string) $page['data'], true) : [];

        $data = is_array($decoded) ? $decoded : [];

        /*
         * Which occasions the tiles show.
         *
         * None ticked means ALL of them — a page called "collections" listing
         * nothing is the one failure it cannot have, and a shop that has not
         * opened the editor yet should still get a working page.
         */
        $collections = model(\App\Models\CollectionModel::class);
        $wanted      = array_map('intval', (array) ($data['tiles']['occasions'] ?? []));

        $tiles = $wanted === []
            ? $collections->occasions(true, true)
            : $collections->whereIn('id', $wanted)->where('is_active', 1)
                ->orderBy('sort_order', 'ASC')->findAll();

        /*
         * The pieces come from the occasions chosen for THIS section, falling
         * back to the tiles' own list, then to everything. So a shop that ticks
         * "Diwali" once gets both the tile and its products without saying so
         * twice.
         */
        $from = array_map('intval', (array) ($data['edit']['source'] ?? []));

        if ($from === []) {
            $from = $wanted !== [] ? $wanted : array_map(
                static fn (array $c): int => (int) $c['id'],
                $tiles
            );
        }

        $productModel = model(\App\Models\ProductModel::class);

        $products = $from === []
            ? []
            : $productModel
                ->whereIn('products.id', static function ($sub) use ($from) {
                    return $sub->select('cp.product_id')
                        ->from('collection_products cp')
                        ->whereIn('cp.collection_id', $from);
                })
                ->applyFilters([])
                ->applySort('featured')
                ->findAll(12);

        $ids = array_map(static fn ($p): int => (int) $p->id, $products);

        return $this->page('storefront/pages/collections', [
            'page'        => $page,
            'data'        => $data,
            'products'    => $products,
            'imageMap'    => model(\App\Models\ProductModel::class)->imagesFor($ids),
            // For the tile fallback when none are configured.
            'tiles'       => $tiles,
            'crumbs'      => [
                ['label' => 'Home', 'url' => site_url()],
                ['label' => $page['title'], 'url' => null],
            ],
        ], [
            'title'       => ($page['meta_title'] ?: $page['title']) . ' · ' . $this->brand->brandName,
            'description' => $page['meta_description'] ?: rs_excerpt((string) $page['excerpt'], 155),
        ]);
    }
}
