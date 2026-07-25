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
        'track_inventory', 'weight_grams', 'unit_label', 'sale_mode',
        'is_giftbox_eligible', 'giftbox_slots', 'is_featured', 'is_active',
        'sort_order', 'meta_title', 'meta_description',
    ];

    protected $validationRules = [
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
            . ') AS primary_image',
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
            $this->where('products.category_id', (int) $filters['category']);
        }

        if (! empty($filters['collection'])) {
            $this->join(
                'collection_products cp',
                'cp.product_id = products.id AND cp.collection_id = ' . (int) $filters['collection'],
                'inner',
                false
            );
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
}
