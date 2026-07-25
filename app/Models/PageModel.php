<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PageModel extends Model
{
    protected $table          = 'pages';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'title', 'slug', 'excerpt', 'content', 'show_in_footer',
        'sort_order', 'is_active', 'meta_title', 'meta_description',
    ];

    protected $validationRules = [
        'title' => 'required|min_length[2]|max_length[191]',
        'slug'  => 'required|max_length[160]|regex_match[/^[a-z0-9-]+$/]|is_unique[pages.slug,id,{id}]',
    ];

    public function findActiveBySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->where('is_active', 1)->first();
    }

    /** @return array<int, array<string, mixed>> */
    public function footerLinks(): array
    {
        return $this->select('title, slug')
            ->where('is_active', 1)
            ->where('show_in_footer', 1)
            ->orderBy('sort_order', 'ASC')
            ->findAll();
    }
}
