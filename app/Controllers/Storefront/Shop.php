<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\CategoryModel;
use App\Models\CollectionModel;
use App\Models\ProductModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Rasmein;

/**
 * Product listing, in four contexts that share one template and one query
 * pipeline: everything, a category, a collection, and a search.
 *
 * All filter input arrives from the query string, so nothing is passed to the
 * model as written — `sort` is matched against a whitelist, prices are cast to
 * float and clamped, flags are cast to bool.
 */
class Shop extends StorefrontController
{
    public function index(): string
    {
        return $this->listing([
            'heading'  => 'Everything',
            'eyebrow'  => 'The shop',
            'intro'    => 'Every item we stock, and every one of them will sit happily in a gift box.',
            'crumbs'   => [['label' => 'Shop', 'url' => null]],
        ]);
    }

    public function category(string $slug): string
    {
        $category = model(CategoryModel::class)->findActiveBySlug($slug);

        if ($category === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->listing([
            'heading'  => $category->name,
            'eyebrow'  => 'Category',
            'intro'    => $category->description,
            'crumbs'   => [
                ['label' => 'Shop', 'url' => site_url('shop')],
                ['label' => $category->name, 'url' => null],
            ],
            'seoTitle' => $category->meta_title ?: $category->name,
            'seoDesc'  => $category->meta_description ?: $category->description,
            'lockedCategory' => $category->id,
        ]);
    }

    public function collection(string $slug): string
    {
        $collection = model(CollectionModel::class)->findActiveBySlug($slug);

        if ($collection === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->listing([
            'heading'  => $collection['name'],
            'eyebrow'  => 'Collection',
            'intro'    => $collection['description'],
            'crumbs'   => [
                ['label' => 'Collections', 'url' => site_url('collections')],
                ['label' => $collection['name'], 'url' => null],
            ],
            'seoTitle' => $collection['meta_title'] ?: $collection['name'],
            'seoDesc'  => $collection['meta_description'] ?: $collection['description'],
            'lockedCollection' => (int) $collection['id'],
        ]);
    }

    public function search(): string
    {
        $term = trim((string) $this->request->getGet('q'));

        return $this->listing([
            'heading'  => $term === '' ? 'Search' : 'Results for “' . $term . '”',
            'eyebrow'  => 'Search',
            'intro'    => $term === '' ? 'Type something above to search the catalogue.' : null,
            'crumbs'   => [['label' => 'Search', 'url' => null]],
            'noindex'  => true,
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $context
     */
    private function listing(array $context): string
    {
        $filters = $this->readFilters($context);
        $sort    = $this->readSort();
        $perPage = config(Rasmein::class)->storefrontPerPage;

        $products = model(ProductModel::class);
        $rows     = $products->applyFilters($filters)->applySort($sort)->paginate($perPage);
        $pager    = $products->pager;

        // Carry the active filters onto the page links, or paging resets them.
        $pager->only(['q', 'sort', 'category', 'min_price', 'max_price', 'in_stock', 'giftable']);

        return $this->page('storefront/shop', [
            'context'     => $context,
            'products'    => $rows,
            'pager'       => $pager,
            'total'       => $pager->getTotal(),
            'filters'     => $filters,
            'sort'        => $sort,
            'sortOptions' => ProductModel::SORTS,
            'categories'  => model(CategoryModel::class)->withProductCounts(),
            'priceRange'  => model(ProductModel::class)->priceRange(),
            'crumbs'      => $context['crumbs'] ?? [],
        ], [
            'title'       => ($context['seoTitle'] ?? $context['heading']) . ' · ' . $this->brand->brandName,
            'description' => rs_excerpt($context['seoDesc'] ?? $context['intro'] ?? $this->brand->brandTagline, 155),
            'noindex'     => (bool) ($context['noindex'] ?? false),
        ]);
    }

    /**
     * Turn the query string into a validated filter array.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function readFilters(array $context): array
    {
        $get = $this->request->getGet();

        $category = $context['lockedCategory'] ?? null;

        // A category page pins its own category; the sidebar cannot override it.
        if ($category === null && ! empty($get['category'])) {
            $category = (int) $get['category'];
        }

        $bounds = model(ProductModel::class)->priceRange();

        $min = isset($get['min_price']) && $get['min_price'] !== ''
            ? max(0.0, (float) $get['min_price'])
            : null;

        $max = isset($get['max_price']) && $get['max_price'] !== ''
            ? min($bounds['max'], (float) $get['max_price'])
            : null;

        // A reversed range would silently return nothing — swap it instead.
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [
            'category'   => $category,
            'collection' => $context['lockedCollection'] ?? null,
            'min_price'  => $min,
            'max_price'  => $max,
            'in_stock'   => ! empty($get['in_stock']),
            'giftable'   => ! empty($get['giftable']),
            'q'          => isset($get['q']) ? trim((string) $get['q']) : null,
        ];
    }

    private function readSort(): string
    {
        $sort = (string) $this->request->getGet('sort');

        return array_key_exists($sort, ProductModel::SORTS) ? $sort : 'featured';
    }
}
