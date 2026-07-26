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
        'id' => 'permit_empty|is_natural_no_zero',   // required by CI4: {id} placeholder
        'title' => 'required|min_length[2]|max_length[191]',
        'slug'  => 'required|max_length[160]|regex_match[/^[a-z0-9-]+$/]|is_unique[pages.slug,id,{id}]',
    ];

    /**
     * Content is sanitised on SAVE, not on render.
     *
     * Storing clean HTML makes every read path safe by construction — including
     * any export, API or email template written later by someone who forgets to
     * escape. The storefront renders pages.content unescaped, and this callback
     * is what makes that safe.
     */
    protected $beforeInsert = ['sanitiseContent'];
    protected $beforeUpdate = ['sanitiseContent'];

    protected function sanitiseContent(array $data): array
    {
        if (isset($data['data']['content'])) {
            $data['data']['content'] = service('sanitiser')->clean($data['data']['content']);
        }

        return $data;
    }

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
