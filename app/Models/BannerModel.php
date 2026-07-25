<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class BannerModel extends Model
{
    protected $table         = 'banners';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'title', 'subtitle', 'eyebrow', 'image', 'mobile_image', 'alt_text',
        'link_url', 'cta_label', 'position', 'sort_order',
        'starts_at', 'ends_at', 'is_active',
    ];

    protected $validationRules = [
        'position'  => 'required|in_list[home_hero,home_strip,category_top,gift_builder]',
        'title'     => 'permit_empty|max_length[191]',
        'subtitle'  => 'permit_empty|max_length[255]',
        'link_url'  => 'permit_empty|max_length[255]',
        'cta_label' => 'permit_empty|max_length[60]',
        'ends_at'   => 'permit_empty|valid_date',
        'starts_at' => 'permit_empty|valid_date',
    ];

    /**
     * Live banners for a slot — active, and inside their scheduling window.
     *
     * @return array<int, array<string, mixed>>
     */
    public function liveFor(string $position, int $limit = 0): array
    {
        $now = date('Y-m-d H:i:s');

        return $this->where('position', $position)
            ->where('is_active', 1)
            ->groupStart()->where('starts_at', null)->orWhere('starts_at <=', $now)->groupEnd()
            ->groupStart()->where('ends_at', null)->orWhere('ends_at >=', $now)->groupEnd()
            ->orderBy('sort_order', 'ASC')
            ->findAll($limit);
    }
}
