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
        'name', 'slug', 'description', 'image', 'is_featured',
        'sort_order', 'is_active', 'meta_title', 'meta_description',
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
}
