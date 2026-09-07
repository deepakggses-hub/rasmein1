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
        'link_url', 'cta_label', 'cta_label_2', 'link_url_2', 'position', 'sort_order',
        'starts_at', 'ends_at', 'is_active',
    ];

    protected $validationRules = [
        'position'  => 'required|in_list[home_hero,corporate_hero,home_strip,home_feature,home_client,home_gallery,category_top,gift_builder]',
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

    /**
     * Is this banner a bare picture, or an image with words over it?
     *
     * The rule, and the reason for it: a shop that uploads only an artwork has
     * already put the words INSIDE the image. Laying a heading and a button on
     * top would double them up and, worse, obscure the very thing that was
     * designed. So a banner with no title and no subtitle renders as the
     * picture alone, wrapped in its link if one was given.
     *
     * The moment any text is entered, the overlay treatment applies — scrim,
     * heading, and buttons — because that text has to be readable over the
     * photograph.
     *
     * @param array<string, mixed> $banner
     */
    public static function isBare(array $banner): bool
    {
        return trim((string) ($banner['title'] ?? '')) === ''
            && trim((string) ($banner['subtitle'] ?? '')) === ''
            && trim((string) ($banner['eyebrow'] ?? '')) === '';
    }

    /**
     * A same-site link for a banner, or null.
     *
     * An editable field that accepted absolute URLs would be a way to point a
     * shop's own hero at somewhere else, so anything with a scheme is refused
     * rather than silently followed.
     *
     * @param array<string, mixed> $banner
     */
    public static function safeLink(array $banner, string $field = 'link_url'): ?string
    {
        $url = trim((string) ($banner[$field] ?? ''));

        if ($url === '' || preg_match('#^[a-z]+://#i', $url) === 1 || str_starts_with($url, '//')) {
            return null;
        }

        return site_url(ltrim($url, '/'));
    }
}
