<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class CollectionModel extends Model
{
    protected $table          = 'collections';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'type', 'name', 'slug', 'description', 'image', 'is_featured',
        'sort_order', 'is_active', 'starts_at', 'ends_at', 'meta_title', 'meta_description',
    ];

    protected $validationRules = [
        'id' => 'permit_empty|is_natural_no_zero',   // required by CI4: {id} placeholder
        'name' => 'required|min_length[2]|max_length[120]',
        'slug' => 'required|max_length[160]|regex_match[/^[a-z0-9-]+$/]|is_unique[collections.slug,id,{id}]',
    ];

    /** @return array<int, array<string, mixed>> */
    public function featured(int $limit = 4): array
    {
        return $this->where('is_active', 1)
            ->where('is_featured', 1)
            ->orderBy('sort_order', 'ASC')
            ->findAll($limit);
    }

    public function findActiveBySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->where('is_active', 1)->first();
    }

    /** @return list<int> */
    public function productIds(int $collectionId): array
    {
        $rows = $this->db->table('collection_products')
            ->select('product_id')
            ->where('collection_id', $collectionId)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $r): int => (int) $r['product_id'], $rows);
    }

    // =================================================================
    // Occasions
    //
    // An occasion IS a collection with type = 'occasion'. Same table, same
    // pivot, same landing page — see migration 000015 for why a parallel table
    // was not worth two copies of the root-URL collision check.
    // =================================================================

    /** @return array<int, array<string, mixed>> */
    public function occasions(bool $activeOnly = false): array
    {
        $query = $this->where('type', 'occasion');

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Occasions a customer should currently see: active, and inside their date
     * window if they have one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function liveOccasions(int $limit = 12): array
    {
        $now = date('Y-m-d H:i:s');

        return $this->where('type', 'occasion')
            ->where('is_active', 1)
            ->groupStart()->where('starts_at', null)->orWhere('starts_at <=', $now)->groupEnd()
            ->groupStart()->where('ends_at', null)->orWhere('ends_at >=', $now)->groupEnd()
            ->orderBy('sort_order', 'ASC')
            ->findAll($limit);
    }

    /**
     * Replace the tagged products wholesale.
     *
     * The form is the complete picture, so this is a replace rather than a
     * merge — otherwise unticking a product would do nothing, which is the
     * kind of silent no-op that erodes trust in a screen.
     *
     * @param list<int> $productIds
     */
    public function syncProducts(int $id, array $productIds): int
    {
        $valid = $productIds === [] ? [] : array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->db->table('products')->select('id')
                ->whereIn('id', array_map('intval', $productIds))
                ->where('deleted_at', null)
                ->get()->getResultArray()
        );

        $this->db->table('collection_products')->where('collection_id', $id)->delete();

        if ($valid === []) {
            return 0;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach (array_values(array_unique($valid)) as $index => $productId) {
            $rows[] = [
                'collection_id' => $id,
                'product_id'    => $productId,
                'sort_order'    => $index,
                'created_at'    => $now,
            ];
        }

        $this->db->table('collection_products')->insertBatch($rows);

        return count($rows);
    }

    /** Which occasions a product is tagged to. @return list<int> */
    public function occasionIdsForProduct(int $productId): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['collection_id'],
            $this->db->table('collection_products')
                ->select('collection_products.collection_id')
                ->join('collections', 'collections.id = collection_products.collection_id')
                ->where('collection_products.product_id', $productId)
                ->where('collections.type', 'occasion')
                ->get()->getResultArray()
        );
    }

    /**
     * Set a product's occasions without disturbing its collections.
     *
     * The pivot holds both, so a naive "delete everything for this product"
     * would silently drop its collection memberships too.
     *
     * @param list<int> $occasionIds
     */
    public function syncProductOccasions(int $productId, array $occasionIds): void
    {
        $all = array_map(
            static fn (array $r): int => (int) $r['id'],
            $this->db->table('collections')->select('id')->where('type', 'occasion')->get()->getResultArray()
        );

        if ($all === []) {
            return;
        }

        // Only occasion rows are cleared; collection rows are left alone.
        $this->db->table('collection_products')
            ->where('product_id', $productId)
            ->whereIn('collection_id', $all)
            ->delete();

        $keep = array_values(array_intersect(array_map('intval', $occasionIds), $all));

        if ($keep === []) {
            return;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach ($keep as $occasionId) {
            $rows[] = [
                'collection_id' => $occasionId,
                'product_id'    => $productId,
                'sort_order'    => 0,
                'created_at'    => $now,
            ];
        }

        $this->db->table('collection_products')->insertBatch($rows);
    }

    /** How many products each occasion holds. @return array<int, int> */
    public function productCounts(): array
    {
        $out = [];

        foreach ($this->db->table('collection_products')
            ->select('collection_id, COUNT(*) AS n', false)
            ->groupBy('collection_id')->get()->getResultArray() as $row) {
            $out[(int) $row['collection_id']] = (int) $row['n'];
        }

        return $out;
    }
}
