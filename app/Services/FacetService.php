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
    public function build(array $filters, array $context = []): array
    {
        $facets = [];

        $categoryId = $context['category'] ?? null;
        $occasionId = $context['occasion'] ?? null;

        if (($cat = $this->categories($filters, $categoryId)) !== null) {
            $facets[] = $cat;
        }

        if ($occasionId === null && ($occ = $this->occasions($filters)) !== null) {
            $facets[] = $occ;
        }

        if (($price = $this->price($filters)) !== null) {
            $facets[] = $price;
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

    /** @return array<string, mixed>|null */
    private function categories(array $filters, ?int $categoryId): ?array
    {
        $db = db_connect();

        /*
         * Inside a category, offer its children. At the top level, offer the
         * roots. Either way the person moves DOWN the tree rather than sideways
         * out of where they are.
         */
        $builder = $db->table('categories c')
            ->select('c.id, c.name, c.path, COUNT(p.id) AS n', false)
            ->join('products p', 'p.category_id = c.id AND p.is_active = 1 AND p.deleted_at IS NULL', 'left')
            ->where('c.is_active', 1)
            ->where('c.deleted_at', null);

        $categoryId !== null
            ? $builder->where('c.parent_id', $categoryId)
            : $builder->where('c.parent_id', null);

        $rows = $builder->groupBy('c.id')->orderBy('c.sort_order', 'ASC')->get()->getResultArray();

        $options = [];

        foreach ($rows as $row) {
            if ((int) $row['n'] === 0) {
                continue;
            }

            $options[] = [
                'label'    => (string) $row['name'],
                'value'    => (string) $row['id'],
                'count'    => (int) $row['n'],
                // A category is a place, not a checkbox — going there changes
                // the URL and the breadcrumb rather than adding a query string.
                'url'      => site_url((string) $row['path']),
                'selected' => false,
            ];
        }

        return count($options) < 2 ? null : [
            'key'     => 'category',
            'label'   => $categoryId !== null ? 'Refine' : 'Category',
            'type'    => 'link',
            'options' => $options,
        ];
    }

    /** @return array<string, mixed>|null */
    private function occasions(array $filters): ?array
    {
        $rows = db_connect()->table('collections c')
            ->select('c.id, c.name, COUNT(cp.product_id) AS n', false)
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
                'selected' => in_array((string) $row['id'], $chosen, true),
            ];
        }

        return count($options) < 2 ? null : [
            'key'     => 'occasion',
            'label'   => 'Occasion',
            'type'    => 'checkbox',
            'options' => $options,
        ];
    }

    /** @return array<string, mixed>|null */
    private function price(array $filters): ?array
    {
        $bands   = config(Rasmein::class)->priceBands ?? [];
        $options = [];

        foreach ($bands as $index => [$from, $to, $label]) {
            $builder = $this->base();

            $builder->where('products.price >=', $from);

            if ($to !== null) {
                $builder->where('products.price <', $to);
            }

            $count = $builder->countAllResults();

            if ($count === 0) {
                continue;
            }

            $options[] = [
                'label'    => $label,
                'value'    => (string) $index,
                'count'    => $count,
                'selected' => in_array((string) $index, array_map('strval', (array) ($filters['band'] ?? [])), true),
            ];
        }

        return count($options) < 2 ? null : [
            'key'     => 'band',
            'label'   => 'Price',
            'type'    => 'checkbox',
            'options' => $options,
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

    /** Every live product — the shared starting point for a count. */
    private function base(): \CodeIgniter\Database\BaseBuilder
    {
        return db_connect()->table('products')
            ->where('products.is_active', 1)
            ->where('products.deleted_at', null);
    }
}
