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

    /**
     * A category at the site root, addressed by its full path.
     *
     * /teas-infusions and /gifting/teas-infusions/green both land here. The
     * catch-all route that feeds this method is registered LAST, so every real
     * route wins first; anything that is not a category falls through to a 404.
     */
    public function path(string ...$segments): string
    {
        $model = model(CategoryModel::class);
        $path  = trim(implode('/', $segments), '/');

        // Cheap rejections before touching the database: a path with a dot is a
        // missing asset, not a category, and an over-long one is a probe.
        if ($path === '' || strlen($path) > 512 || str_contains($path, '.')) {
            throw PageNotFoundException::forPageNotFound();
        }

        // One authority decides what lives here, so a category and an occasion
        // can never both answer on the same address.
        $hit = service('rootUrls')->resolve($path);

        if ($hit === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $hit['kind'] === 'occasion'
            ? $this->renderOccasion($hit['entity'])
            : $this->renderCategory($hit['entity']);
    }

    /**
     * The old /shop/{slug} address.
     *
     * Kept as a permanent redirect rather than deleted: those URLs may already
     * be in someone's history, a printed card, or a search index, and silently
     * 404ing them loses traffic that was earned.
     */
    public function category(string $slug)
    {
        $category = model(CategoryModel::class)->findActiveBySlug($slug);

        if ($category === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(site_url((string) ($category->path ?: $category->slug)), 301);
    }

    /**
     * An occasion page: every product tagged to it.
     *
     * @param array<string, mixed> $occasion CollectionModel returns arrays,
     *                                        unlike CategoryModel.
     */
    private function renderOccasion(array $occasion): string
    {
        $ends = $occasion['ends_at'] ?? null;

        return $this->listing([
            'heading'  => $occasion['name'],
            'eyebrow'  => 'Occasion',
            'intro'    => $occasion['description'],
            'crumbs'   => [
                ['label' => 'Shop', 'url' => site_url('shop')],
                ['label' => $occasion['name'], 'url' => null],
            ],
            'seoTitle' => $occasion['meta_title'] ?: $occasion['name'],
            'seoDesc'  => $occasion['meta_description'] ?: $occasion['description'],
            'lockedCollection' => (int) $occasion['id'],
            // Shown on the page so a seasonal occasion says how long is left,
            // which is the whole reason someone is looking at it.
            'endsAt'   => $ends,
        ]);
    }

    /** @param object $category */
    private function renderCategory($category): string
    {
        $model = model(CategoryModel::class);
        $id    = (int) $category->id;

        $crumbs = [['label' => 'Shop', 'url' => site_url('shop')]];

        foreach ($model->ancestors($id) as $ancestor) {
            $crumbs[] = ['label' => $ancestor->name, 'url' => site_url((string) $ancestor->path)];
        }

        $crumbs[] = ['label' => $category->name, 'url' => null];

        return $this->listing([
            'heading'  => $category->name,
            'eyebrow'  => 'Category',
            'intro'    => $category->description,
            'crumbs'   => $crumbs,
            'seoTitle' => $category->meta_title ?: $category->name,
            'seoDesc'  => $category->meta_description ?: $category->description,
            // The category AND everything beneath it.
            'lockedCategory' => $model->descendantIds($id),
            'children'       => $model->childrenOf($id),
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
            // Subcategories of the category being viewed, so a parent page
            // offers a way down rather than only a flat product list.
            'children'    => $context['children'] ?? [],
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
