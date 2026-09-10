<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Product;
use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table          = 'products';
    protected $primaryKey     = 'id';
    protected $returnType     = Product::class;
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'category_id', 'sku', 'name', 'slug', 'short_description', 'description',
        'price', 'compare_at_price', 'stock_qty', 'low_stock_threshold',
        'track_inventory', 'weight_grams', 'unit_label', 'material',
        'eyebrow_label', 'composition', 'packaging_note', 'care_note',
        'personalisation_note', 'rating_average', 'review_count', 'sale_mode', 'audience',
        'is_giftbox_eligible', 'giftbox_slots', 'is_featured', 'is_active',
        'sort_order', 'meta_title', 'meta_description',
    ];

    protected $validationRules = [
        'id' => 'permit_empty|is_natural_no_zero',   // required by CI4: {id} placeholder
        'sku'                 => 'required|max_length[60]|is_unique[products.sku,id,{id}]',
        'name'                => 'required|min_length[2]|max_length[191]',
        'slug'                => 'required|max_length[200]|regex_match[/^[a-z0-9-]+$/]|is_unique[products.slug,id,{id}]',
        'price'               => 'required|decimal|greater_than_equal_to[0]',
        'compare_at_price'    => 'permit_empty|decimal|greater_than_equal_to[0]',
        'stock_qty'           => 'permit_empty|is_integer|greater_than_equal_to[0]',
        'low_stock_threshold' => 'permit_empty|is_integer|greater_than_equal_to[0]',
        'category_id'         => 'permit_empty|is_natural_no_zero',
        'sale_mode'           => 'required|in_list[inherit,buy_now,enquire_now]',
        'giftbox_slots'       => 'required|is_natural_no_zero|less_than_equal_to[24]',
        'short_description'   => 'permit_empty|max_length[255]',
        'meta_title'          => 'permit_empty|max_length[191]',
        'meta_description'    => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'sku' => [
            'is_unique' => 'That SKU is already in use by another product.',
        ],
        'slug' => [
            'regex_match' => 'The URL slug may only contain lowercase letters, numbers and hyphens.',
            'is_unique'   => 'Another product already uses that URL slug.',
        ],
        'giftbox_slots' => [
            'less_than_equal_to' => 'A single product cannot fill more than 24 compartments.',
        ],
    ];

    protected $beforeInsert = ['sanitiseDescription'];
    protected $beforeUpdate = ['sanitiseDescription'];

    /**
     * The description is now authored in a rich text editor, so it is HTML and
     * the storefront renders it unescaped. That is only safe because it is
     * sanitised here on save, through the same allowlist the CMS pages use.
     */
    protected function sanitiseDescription(array $data): array
    {
        if (isset($data['data']['description'])) {
            $data['data']['description'] = service('sanitiser')->clean($data['data']['description']);
        }

        return $data;
    }


    /**
     * Adds the primary image path as `primary_image` without duplicating rows.
     * A correlated subquery is cheaper here than a join plus GROUP BY.
     */
    public function withPrimaryImage(): self
    {
        // escape = false: this is a hand-written expression, and CI4's
        // identifier protection would rewrite the keywords inside it.
        $this->select(
            'products.*, ('
            . 'SELECT pi.path FROM product_images pi'
            . ' WHERE pi.product_id = products.id'
            . ' ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC'
            . ' LIMIT 1'
            . ') AS primary_image, ('
            // The card shows a category eyebrow (RITUAL · PUJA in the design).
            // A correlated select rather than a join: a join here would have to
            // be repeated by every caller that adds its own, and a LEFT JOIN
            // interacts badly with the whereIn subqueries the filters use.
            . 'SELECT c.name FROM categories c WHERE c.id = products.category_id'
            . ') AS category_name',
            false
        );

        return $this;
    }

    /** Only rows a storefront visitor may see. */
    public function scopeVisible(): self
    {
        $this->where('products.is_active', 1);

        return $this;
    }

    /** @return list<Product> */
    public function featured(int $limit = 8): array
    {
        return $this->withPrimaryImage()
            ->scopeVisible()
            ->where('products.is_featured', 1)
            ->orderBy('products.sort_order', 'ASC')
            ->orderBy('products.id', 'DESC')
            ->findAll($limit);
    }

    /** @return list<Product> */
    public function latest(int $limit = 8): array
    {
        return $this->withPrimaryImage()
            ->scopeVisible()
            ->orderBy('products.id', 'DESC')
            ->findAll($limit);
    }

    /** @return list<Product> */
    public function giftBoxEligible(int $limit = 0): array
    {
        return $this->withPrimaryImage()
            ->scopeVisible()
            ->where('products.is_giftbox_eligible', 1)
            ->orderBy('products.sort_order', 'ASC')
            ->findAll($limit);
    }

    public function findVisibleBySlug(string $slug): ?Product
    {
        return $this->withPrimaryImage()
            ->scopeVisible()
            ->where('products.slug', $slug)
            ->first();
    }

    /** @return list<Product> */
    public function inCategory(int $categoryId, int $limit = 0): array
    {
        return $this->withPrimaryImage()
            ->scopeVisible()
            ->where('products.category_id', $categoryId)
            ->orderBy('products.sort_order', 'ASC')
            ->findAll($limit);
    }

    /**
     * Decrement stock atomically. Returns false when the reduction would take
     * the product below zero, so two concurrent checkouts cannot oversell.
     */
    public function reserveStock(int $productId, int $quantity): bool
    {
        $sql = 'UPDATE products SET stock_qty = stock_qty - ?'
            . ' WHERE id = ? AND (track_inventory = 0 OR stock_qty >= ?)';

        $this->db->query($sql, [$quantity, $productId, $quantity]);

        return $this->db->affectedRows() === 1;
    }

    // ==================================================================
    // Storefront browsing: filtering, search, sorting, pagination
    // ==================================================================

    /**
     * Sort keys the storefront offers. Anything not in this list is ignored
     * rather than passed through — an ORDER BY must never come from a query
     * string unchecked.
     */
    public const SORTS = [
        'featured'   => 'Featured',
        'newest'     => 'Newest first',
        'price_asc'  => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'name_asc'   => 'Name: A to Z',
    ];

    /**
     * Apply storefront filters.
     *
     * Every value is validated or cast here. `category`, `sort` and the price
     * bounds arrive from the URL, so none of them is trusted as written.
     *
     * @param array{
     *     category?: int|null, collection?: int|null, min_price?: float|null,
     *     max_price?: float|null, in_stock?: bool, giftable?: bool, q?: string|null
     * } $filters
     */
    public function applyFilters(array $filters): self
    {
        $this->withPrimaryImage()->scopeVisible();

        if (! empty($filters['category'])) {
            // A list, not just an id: opening a parent category must show what
            // is in its subcategories too, or a top-level page looks empty
            // while everything sits one level down.
            $ids = array_values(array_filter(
                array_map('intval', (array) $filters['category']),
                static fn (int $id): bool => $id > 0
            ));

            if ($ids !== []) {
                count($ids) === 1
                    ? $this->where('products.category_id', $ids[0])
                    : $this->whereIn('products.category_id', $ids);
            }
        }

        if (! empty($filters['collection'])) {
            $this->join(
                'collection_products cp',
                'cp.product_id = products.id AND cp.collection_id = ' . (int) $filters['collection'],
                'inner',
                false
            );
        }

        /*
         * Only what belongs on the shop the visitor is looking at.
         *
         * Applied ALWAYS, not as an optional filter: a corporate-only bulk set
         * appearing in the ordinary shop is the bug this exists to prevent, and
         * an opt-in filter is one someone forgets to pass.
         *
         * `both` always shows, so an untagged catalogue behaves exactly as it
         * did before the column existed.
         */
        $audience = service('settings')->journeyMode() === \Config\Rasmein::MODE_ENQUIRE
            ? 'corporate'
            : 'retail';

        $this->whereIn('products.audience', ['both', $audience]);

        if (! empty($filters['attrs'])) {
            /*
             * Within one attribute the values are OR (silver or gold); ACROSS
             * attributes they are AND (silver AND large). That is what a
             * shopper means, and it needs one subquery per attribute — a single
             * whereIn over all of them would return anything matching any value.
             */
            foreach ($filters['attrs'] as $values) {
                $ids = array_values(array_filter(array_map('intval', (array) $values)));

                if ($ids === []) {
                    continue;
                }

                $this->whereIn('products.id', static function ($sub) use ($ids) {
                    return $sub->select('pa.product_id')
                        ->from('product_attributes pa')
                        ->whereIn('pa.value_id', $ids);
                });
            }
        }

        if (! empty($filters['categories'])) {
            // Narrowing within the page, so no descendant walk: these ids came
            // from the facet, which already listed only categories present here.
            $this->whereIn('products.category_id', array_map('intval', (array) $filters['categories']));
        }

        if (! empty($filters['material'])) {
            $materials = array_values(array_filter(array_map('strval', (array) $filters['material'])));

            if ($materials !== []) {
                $this->whereIn('products.material', $materials);
            }
        }

        if (! empty($filters['occasion'])) {
            // Occasions live in the collections pivot; a subquery keeps this a
            // filter rather than a join that would duplicate rows.
            $this->whereIn('products.id', static function ($sub) use ($filters) {
                return $sub->select('cp.product_id')
                    ->from('collection_products cp')
                    ->join('collections c', 'c.id = cp.collection_id')
                    ->where('c.type', 'occasion')
                    ->whereIn('cp.collection_id', array_map('intval', (array) $filters['occasion']));
            });
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== null) {
            $this->where('products.price >=', (float) $filters['min_price']);
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== null) {
            $this->where('products.price <=', (float) $filters['max_price']);
        }

        if (! empty($filters['in_stock'])) {
            $this->groupStart()
                ->where('products.track_inventory', 0)
                ->orWhere('products.stock_qty >', 0)
                ->groupEnd();
        }

        if (! empty($filters['giftable'])) {
            $this->where('products.is_giftbox_eligible', 1);
        }

        if (! empty($filters['q'])) {
            $this->applySearch((string) $filters['q']);
        }

        return $this;
    }

    /**
     * Text search.
     *
     * Uses the FULLTEXT index for terms long enough to be indexed
     * (innodb_ft_min_token_size is 3 by default) and falls back to LIKE for
     * shorter ones, so "tea" and "ce" both behave sensibly. The term is passed
     * through the driver's escaper — never concatenated raw.
     */
    /**
     * Text search.
     *
     * MySQL's FULLTEXT boolean mode treats + - > < ( ) ~ * " @ as operators, and
     * a malformed expression is a hard SQL error, not an empty result. So every
     * token is reduced to letters and digits before it goes anywhere near the
     * query. That is not only an injection concern — a customer typing
     * "tea (loose)" would otherwise crash the page.
     *
     * \p{L}\p{N} rather than \w so Devanagari and other scripts survive.
     *
     * Tokens shorter than innodb_ft_min_token_size (3 by default) are not in
     * the index at all, so those fall back to LIKE.
     */
    public function applySearch(string $term): self
    {
        $term = trim(preg_replace('/\s+/u', ' ', $term) ?? '');

        if ($term === '') {
            return $this;
        }

        // Guard against someone pasting an essay into the search box.
        $term = mb_substr($term, 0, 120);

        $tokens = [];

        foreach (explode(' ', $term) as $word) {
            $clean = preg_replace('/[^\p{L}\p{N}]+/u', '', $word) ?? '';

            if (mb_strlen($clean) >= 3) {
                $tokens[] = $clean;
            }
        }

        if ($tokens !== [] && $this->db->DBDriver === 'MySQLi') {
            // Trailing wildcard gives prefix matching, so "choc" finds
            // "chocolate". Every token is required (+).
            $expression = implode(' ', array_map(
                static fn (string $t): string => '+' . $t . '*',
                $tokens
            ));

            // FULLTEXT covers name and description, but not SKU — and staff
            // search by SKU constantly ("RSM-CH-001"), which tokenises into
            // fragments the index will never match. So the relevance match is
            // OR'd with a plain LIKE on SKU and name. CI4 escapes LIKE
            // wildcards, so % and _ in the term are literal.
            $this->groupStart()
                ->where(
                    'MATCH(products.name, products.short_description, products.description) '
                    . 'AGAINST (' . $this->db->escape($expression) . ' IN BOOLEAN MODE)',
                    null,
                    false
                )
                ->orLike('products.sku', $term)
                ->orLike('products.name', $term)
                ->groupEnd();

            return $this;
        }

        // Short or symbol-only terms: LIKE. CI4 escapes % and _ for us.
        $this->groupStart()
            ->like('products.name', $term)
            ->orLike('products.short_description', $term)
            ->orLike('products.sku', $term)
            ->groupEnd();

        return $this;
    }

    /** Whitelisted sort. An unknown key falls back to Featured. */
    public function applySort(?string $sort): self
    {
        return match ($sort) {
            'newest'     => $this->orderBy('products.id', 'DESC'),
            'price_asc'  => $this->orderBy('products.price', 'ASC'),
            'price_desc' => $this->orderBy('products.price', 'DESC'),
            'name_asc'   => $this->orderBy('products.name', 'ASC'),
            default      => $this->orderBy('products.is_featured', 'DESC')
                ->orderBy('products.sort_order', 'ASC')
                ->orderBy('products.id', 'DESC'),
        };
    }

    /**
     * The lowest and highest visible price, for the price-filter bounds.
     *
     * @return array{min: float, max: float}
     */
    public function priceRange(): array
    {
        $row = $this->builder()
            ->select('MIN(price) AS min_price, MAX(price) AS max_price', false)
            ->where('is_active', 1)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return [
            'min' => (float) ($row['min_price'] ?? 0),
            'max' => (float) ($row['max_price'] ?? 0),
        ];
    }

    /**
     * Other products worth showing beside this one: same category first,
     * topped up with featured items if the category is thin.
     *
     * @return list<Product>
     */
    public function related(Product $product, int $limit = 4): array
    {
        $related = [];

        if ($product->category_id !== null) {
            $related = $this->withPrimaryImage()
                ->scopeVisible()
                ->where('products.category_id', $product->category_id)
                ->where('products.id !=', $product->id)
                ->orderBy('products.is_featured', 'DESC')
                ->orderBy('RAND()', '', false)
                ->findAll($limit);
        }

        if (count($related) >= $limit) {
            return $related;
        }

        $seen = array_map(static fn (Product $p): int => $p->id, $related);
        $seen[] = $product->id;

        $filler = $this->withPrimaryImage()
            ->scopeVisible()
            ->whereNotIn('products.id', $seen)
            ->orderBy('products.is_featured', 'DESC')
            ->findAll($limit - count($related));

        return array_merge($related, $filler);
    }

    /**
     * Every image for a set of products, in ONE query.
     *
     * The listing card cycles through a product's photographs on hover, which
     * means it needs them all. Asking per card is an N+1 — twelve products on a
     * page is twelve extra queries, and the shop grid can show far more than
     * twelve. One query, grouped in PHP, costs the same at any page size.
     *
     * @param list<int> $productIds
     *
     * @return array<int, array<int, array<string, mixed>>> Keyed by product id
     */
    public function imagesFor(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if ($ids === []) {
            return [];
        }

        $out = [];

        foreach ($this->db->table('product_images')
            ->select('product_id, path, alt_text')
            ->whereIn('product_id', $ids)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()->getResultArray() as $row) {
            $out[(int) $row['product_id']][] = $row;
        }

        return $out;
    }
}
