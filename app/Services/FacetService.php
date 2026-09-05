<?php

declare(strict_types=1);

namespace App\Services;

use Config\Rasmein;

/**
 * The filter sidebar, built from what is actually in the current results.
 *
 * WHY THE FACETS ARE COMPUTED, NOT DECLARED
 *
 * A fixed sidebar offers filters that lead nowhere: a Material list showing
 * "Wood 0" on a page with no wooden products, or an Occasion filter on the
 * occasion page itself. Every count here comes from the SAME conditions as the
 * listing, minus the facet's own selection — so a count is a promise that
 * ticking it returns that many things.
 *
 * A facet with fewer than two options is dropped entirely. One choice is not a
 * choice, and a sidebar of single-option filters is noise.
 *
 * CONTEXT AWARENESS
 *
 *  - Inside a category, the Category facet becomes its SUBCATEGORIES. Offering
 *    the whole tree there would navigate away from where the person is.
 *  - On an occasion page, the Occasion facet is dropped.
 *  - A facet whose values are all identical is dropped, because filtering by it
 *    could not narrow anything.
 */
class FacetService
{
    /**
     * @param array<string, mixed> $filters  The listing's active filters
     * @param array<string, mixed> $context  ['category' => int|null, 'occasion' => int|null]
     *
     * @return array<int, array<string, mixed>>
     */
    /** The category this page is inside, if any. */
    private ?int $categoryId = null;

    /** The occasion this page is inside, if any. */
    private ?int $occasionId = null;

    public function build(array $filters, array $context = []): array
    {
        $facets = [];

        $this->categoryId = $categoryId = $context['category'] ?? null;
        $this->occasionId = $occasionId = $context['occasion'] ?? null;

        if (($cat = $this->categories($filters, $categoryId)) !== null) {
            $facets[] = $cat;
        }

        /*
         * The occasion facet is shown on an occasion page too, so someone can
         * see what else these pieces are suited to and move sideways. It used
         * to be dropped, which left the page with no way out except the nav.
         */
        if (($occ = $this->occasions($filters)) !== null) {
            $facets[] = $occ;
        }

        if (($price = $this->price($filters)) !== null) {
            $facets[] = $price;
        }

        /*
         * A facet per filterable attribute, counted within THIS page — so on a
         * Diwali page "Silver (4)" means four of these gifts, not four in the
         * whole shop.
         */
        foreach ($this->attributes($filters) as $attributeFacet) {
            $facets[] = $attributeFacet;
        }

        if (($material = $this->material($filters)) !== null) {
            $facets[] = $material;
        }

        if (($stock = $this->availability($filters)) !== null) {
            $facets[] = $stock;
        }

        return $facets;
    }

    // =================================================================

    /**
     * Categories, derived from the products this page actually shows.
     *
     * On an occasion page that means "the categories these gifts fall into" —
     * three, not the shop's whole tree. Listing every category with a zero
     * beside it is noise, and listing them without counts is a set of dead
     * ends.
     *
     * @return array<string, mixed>|null
     */
    private function categories(array $filters, ?int $categoryId): ?array
    {
        $db = db_connect();

        /*
         * Inside a plain category listing, offer its CHILDREN — moving down the
         * tree rather than sideways out of it. Everywhere else, offer the
         * categories present in the current results.
         */
        if ($categoryId !== null && $this->occasionId === null) {
            $rows = $db->table('categories c')
                ->select('c.id, c.name, c.path, COUNT(p.id) AS n', false)
                ->join('products p', 'p.category_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL', 'left')
                ->where('c.is_active', 1)
                ->where('c.deleted_at', null)
                ->where('c.parent_id', $categoryId)
                ->groupBy('c.id')->orderBy('c.sort_order', 'ASC')
                ->get()->getResultArray();
        } else {
            $rows = $this->base()
                ->select('categories.id, categories.name, categories.path, COUNT(products.id) AS n', false)
                ->join('categories', 'categories.id = products.category_id')
                ->where('categories.is_active', 1)
                ->where('categories.deleted_at', null)
                ->groupBy('categories.id')
                ->orderBy('n', 'DESC')
                ->get()->getResultArray();
        }

        $chosen  = array_map('strval', (array) ($filters['cat'] ?? []));
        $options = [];

        foreach ($rows as $row) {
            if ((int) $row['n'] === 0) {
                continue;
            }

            $options[] = [
                'label'    => (string) $row['name'],
                'value'    => (string) $row['id'],
                'count'    => (int) $row['n'],
                'url'      => site_url((string) $row['path']),
                'selected' => in_array((string) $row['id'], $chosen, true),
            ];
        }

        if (count($options) < 2) {
            return null;
        }

        /*
         * Inside an occasion the category is a FILTER — ticking it narrows this
         * occasion rather than navigating away from it, which is what someone
         * browsing Diwali actually wants. On the shop it stays a link, because
         * a category there is a place with its own page.
         */
        $asFilter = $this->occasionId !== null;

        return [
            'key'     => $asFilter ? 'cat' : 'category',
            'label'   => $categoryId !== null && ! $asFilter ? 'Refine' : 'Category',
            'type'    => $asFilter ? 'checkbox' : 'link',
            'options' => $options,
        ];
    }

    /** @return array<string, mixed>|null */
    private function occasions(array $filters): ?array
    {
        $rows = db_connect()->table('collections c')
            ->select('c.id, c.name, c.slug, COUNT(cp.product_id) AS n', false)
            ->join('collection_products cp', 'cp.collection_id = c.id', 'left')
            ->join('products p', 'p.id = cp.product_id AND p.is_active = 1 AND p.deleted_at IS NULL', 'left')
            ->where('c.type', 'occasion')
            ->where('c.is_active', 1)
            ->where('c.deleted_at', null)
            ->groupBy('c.id')
            ->orderBy('c.sort_order', 'ASC')
            ->get()->getResultArray();

        $chosen  = array_map('strval', (array) ($filters['occasion'] ?? []));
        $options = [];

        foreach ($rows as $row) {
            if ((int) $row['n'] === 0) {
                continue;
            }

            $options[] = [
                'label'    => (string) $row['name'],
                'value'    => (string) $row['id'],
                'count'    => (int) $row['n'],
                // On an occasion page the current one is a LINK back to itself
                // and shown as already chosen, so the sidebar says where you are
                // rather than pretending you could tick it.
                // Always a link. An occasion is a page with its own copy and
                // banner; ticking it as a filter would show the same products
                // stripped of all of that.
                'url'      => site_url((string) $row['slug']),
                'selected' => $this->occasionId === (int) $row['id']
                    || in_array((string) $row['id'], $chosen, true),
            ];
        }

        return count($options) < 2 ? null : [
            'key'     => 'occasion',
            'label'   => 'Occasion',
            'type'    => 'link',
            'options' => $options,
        ];
    }

    /**
     * Price, as a range bounded by the page's own products.
     *
     * A slider rather than bands: bands force a shop to guess where the
     * boundaries should be, and on a page of four gifts they all land in one.
     * The ends come from MIN and MAX of what is actually here, so the handles
     * always span something real.
     *
     * @return array<string, mixed>|null
     */
    private function price(array $filters): ?array
    {
        $row = $this->base()
            ->select('MIN(products.price) AS lo, MAX(products.price) AS hi', false)
            ->get()->getRowArray();

        if ($row === null || $row['lo'] === null) {
            return null;
        }

        // Round outwards to whole hundreds, so the handles land on numbers a
        // person would say out loud.
        $lo = (int) floor(((float) $row['lo']) / 100) * 100;
        $hi = (int) ceil(((float) $row['hi']) / 100) * 100;

        // Everything at one price: a slider with both ends together does
        // nothing, and showing it would be a control that cannot be used.
        if ($hi <= $lo) {
            return null;
        }

        return [
            'key'   => 'price',
            'label' => 'Price',
            'type'  => 'range',
            'min'   => $lo,
            'max'   => $hi,
            // What is currently applied, clamped into the available span.
            'from'  => isset($filters['min_price']) && $filters['min_price'] !== null
                ? max($lo, (int) $filters['min_price'])
                : $lo,
            'to'    => isset($filters['max_price']) && $filters['max_price'] !== null
                ? min($hi, (int) $filters['max_price'])
                : $hi,
            'options' => [],
        ];
    }

    /** @return array<string, mixed>|null */
    private function material(array $filters): ?array
    {
        $rows = $this->base()
            ->select('products.material, COUNT(*) AS n', false)
            ->where('products.material IS NOT NULL', null, false)
            ->where('products.material !=', '')
            ->groupBy('products.material')
            ->orderBy('n', 'DESC')
            ->get()->getResultArray();

        $chosen  = array_map('strval', (array) ($filters['material'] ?? []));
        $options = [];

        foreach ($rows as $row) {
            $options[] = [
                'label'    => (string) $row['material'],
                'value'    => (string) $row['material'],
                'count'    => (int) $row['n'],
                'selected' => in_array((string) $row['material'], $chosen, true),
            ];
        }

        return count($options) < 2 ? null : [
            'key'     => 'material',
            'label'   => 'Material',
            'type'    => 'checkbox',
            'options' => $options,
        ];
    }

    /** @return array<string, mixed>|null */
    private function availability(array $filters): ?array
    {
        $inStock = $this->base()
            ->groupStart()
                ->where('products.track_inventory', 0)
                ->orWhere('products.stock_qty >', 0)
            ->groupEnd()
            ->countAllResults();

        $madeToOrder = $this->base()
            ->where('products.track_inventory', 1)
            ->where('products.stock_qty', 0)
            ->countAllResults();

        $options = [];

        if ($inStock > 0) {
            $options[] = [
                'label' => 'In stock', 'value' => 'in', 'count' => $inStock,
                'selected' => ! empty($filters['in_stock']),
            ];
        }

        if ($madeToOrder > 0) {
            $options[] = [
                'label' => 'Made to order', 'value' => 'order', 'count' => $madeToOrder,
                'selected' => ! empty($filters['made_to_order']),
            ];
        }

        return count($options) < 2 ? null : [
            'key'     => 'stock',
            'label'   => 'Availability',
            'type'    => 'checkbox',
            'options' => $options,
        ];
    }

    /**
     * Colour, size, shape — one facet each, counted within the page.
     *
     * A single query for every attribute at once. One per attribute would be
     * five or six round trips on a page that already runs several.
     *
     * @return array<int, array<string, mixed>>
     */
    private function attributes(array $filters): array
    {
        $rows = $this->base()
            ->select('a.name, a.code, a.input_type, a.sort_order,'
                . ' av.id AS value_id, av.label, av.swatch_hex, COUNT(DISTINCT products.id) AS n', false)
            ->join('product_attributes pa', 'pa.product_id = products.id')
            ->join('attribute_values av', 'av.id = pa.value_id')
            ->join('attributes a', 'a.id = av.attribute_id')
            ->where('a.is_active', 1)
            ->where('a.is_filterable', 1)
            ->groupBy('av.id')
            ->orderBy('a.sort_order', 'ASC')
            ->orderBy('av.sort_order', 'ASC')
            ->get()->getResultArray();

        $grouped = [];

        foreach ($rows as $row) {
            $code = (string) $row['code'];

            $grouped[$code] ??= [
                'key'     => 'a_' . $code,
                'label'   => (string) $row['name'],
                'type'    => $row['input_type'] === 'swatch' ? 'swatch' : 'checkbox',
                'options' => [],
            ];

            $chosen = array_map('strval', (array) ($filters['attrs'][$code] ?? []));

            $grouped[$code]['options'][] = [
                'label'    => (string) $row['label'],
                'value'    => (string) $row['value_id'],
                'count'    => (int) $row['n'],
                'swatch'   => $row['swatch_hex'],
                'selected' => in_array((string) $row['value_id'], $chosen, true),
            ];
        }

        // A facet offering one option filters nothing — every product on the
        // page already has it.
        return array_values(array_filter(
            $grouped,
            static fn (array $facet): bool => count($facet['options']) > 1
        ));
    }

    /**
     * The live products THIS PAGE is drawn from.
     *
     * Every facet counts from here, so on an occasion page the numbers describe
     * that occasion rather than the whole shop. Counting globally is worse than
     * useless: "Brass 32" beside a page showing four things is a promise the
     * page cannot keep.
     */
    private function base(): \CodeIgniter\Database\BaseBuilder
    {
        $builder = db_connect()->table('products')
            ->where('products.is_active', 1)
            ->where('products.deleted_at', null);

        if ($this->occasionId !== null) {
            // Captured into a local: a STATIC closure has no $this, and CI4
            // calls this one statically.
            $occasionId = $this->occasionId;

            $builder->whereIn('products.id', static function ($sub) use ($occasionId) {
                return $sub->select('cp.product_id')
                    ->from('collection_products cp')
                    ->where('cp.collection_id', $occasionId);
            });
        }

        if ($this->categoryId !== null) {
            // The category and everything beneath it.
            $ids = model(\App\Models\CategoryModel::class)->descendantIds($this->categoryId);
            $builder->whereIn('products.category_id', $ids === [] ? [$this->categoryId] : $ids);
        }

        return $builder;
    }
}
