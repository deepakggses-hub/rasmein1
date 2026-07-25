<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Category;
use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table          = 'categories';
    protected $primaryKey     = 'id';
    protected $returnType     = Category::class;
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'parent_id', 'name', 'slug', 'description', 'image',
        'is_featured', 'sort_order', 'is_active',
        'meta_title', 'meta_description',
    ];

    protected $validationRules = [
        'name'             => 'required|min_length[2]|max_length[120]',
        'slug'             => 'required|max_length[160]|regex_match[/^[a-z0-9-]+$/]|is_unique[categories.slug,id,{id}]',
        'parent_id'        => 'permit_empty|is_natural_no_zero',
        'sort_order'       => 'permit_empty|is_integer',
        'meta_title'       => 'permit_empty|max_length[191]',
        'meta_description' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'slug' => [
            'regex_match' => 'The URL slug may only contain lowercase letters, numbers and hyphens.',
            'is_unique'   => 'Another category already uses that URL slug.',
        ],
    ];

    /** @return list<Category> */
    public function activeTopLevel(): array
    {
        return $this->where('is_active', 1)
            ->groupStart()->where('parent_id', null)->orWhere('parent_id', 0)->groupEnd()
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /** @return list<Category> */
    public function featured(int $limit = 6): array
    {
        return $this->where('is_active', 1)
            ->where('is_featured', 1)
            ->orderBy('sort_order', 'ASC')
            ->findAll($limit);
    }

    public function findActiveBySlug(string $slug): ?Category
    {
        return $this->where('slug', $slug)->where('is_active', 1)->first();
    }

    /**
     * Categories with a live count of purchasable products.
     *
     * @return list<Category>
     */
    public function withProductCounts(bool $activeOnly = true, ?int $limit = null): array
    {
        $builder = $this->select('categories.*, COUNT(products.id) AS product_count')
            ->join('products', 'products.category_id = categories.id AND products.is_active = 1 AND products.deleted_at IS NULL', 'left', false)
            ->groupBy('categories.id')
            ->orderBy('categories.sort_order', 'ASC');

        if ($activeOnly) {
            $builder->where('categories.is_active', 1);
        }

        return $builder->findAll($limit ?? 0);
    }
}
