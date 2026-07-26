<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\GiftBox;
use CodeIgniter\Model;

class GiftBoxModel extends Model
{
    protected $table          = 'gift_boxes';
    protected $primaryKey     = 'id';
    protected $returnType     = GiftBox::class;
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'name', 'slug', 'description', 'image', 'base_price',
        'capacity_slots', 'min_slots', 'size_label', 'theme', 'price_tier',
        'sale_mode', 'allow_gift_message', 'gift_message_max_chars',
        'allow_special_note', 'is_featured', 'sort_order', 'is_active',
        'meta_title', 'meta_description',
    ];

    protected $validationRules = [
        'id' => 'permit_empty|is_natural_no_zero',   // required by CI4: {id} placeholder
        'name'                   => 'required|min_length[2]|max_length[120]',
        'slug'                   => 'required|max_length[160]|regex_match[/^[a-z0-9-]+$/]|is_unique[gift_boxes.slug,id,{id}]',
        'base_price'             => 'required|decimal|greater_than_equal_to[0]',
        'capacity_slots'         => 'required|is_natural_no_zero|less_than_equal_to[24]',
        'min_slots'              => 'required|is_natural|less_than_equal_to[24]',
        'sale_mode'              => 'required|in_list[inherit,buy_now,enquire_now]',
        'gift_message_max_chars' => 'permit_empty|is_natural|less_than_equal_to[1000]',
    ];

    protected $validationMessages = [
        'capacity_slots' => [
            'less_than_equal_to' => 'A box cannot have more than 24 compartments.',
        ],
    ];

    /**
     * min_slots can never exceed capacity_slots. Enforced here rather than in a
     * controller so it holds for the admin form, a seeder, and an import alike.
     */
    protected $beforeInsert = ['clampSlots', 'sanitiseDescription'];
    protected $beforeUpdate = ['clampSlots', 'sanitiseDescription'];

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

    protected function clampSlots(array $data): array
    {
        $capacity = isset($data['data']['capacity_slots']) ? (int) $data['data']['capacity_slots'] : null;
        $minimum  = isset($data['data']['min_slots']) ? (int) $data['data']['min_slots'] : null;

        if ($capacity !== null && $minimum !== null && $minimum > $capacity) {
            $data['data']['min_slots'] = $capacity;
        }

        return $data;
    }

    /** @return list<GiftBox> */
    public function activeBoxes(int $limit = 0): array
    {
        return $this->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('base_price', 'ASC')
            ->findAll($limit);
    }

    /** @return list<GiftBox> */
    public function featured(int $limit = 3): array
    {
        return $this->where('is_active', 1)
            ->where('is_featured', 1)
            ->orderBy('sort_order', 'ASC')
            ->findAll($limit);
    }

    public function findActiveBySlug(string $slug): ?GiftBox
    {
        return $this->where('slug', $slug)->where('is_active', 1)->first();
    }

    /**
     * Products a given box may contain.
     *
     * Resolution order:
     *   1. Start from the box's allowed categories — or every gift-box-eligible
     *      product if the box lists no categories.
     *   2. Add products explicitly allowed for this box.
     *   3. Remove products explicitly excluded for this box (exclusion wins).
     *
     * This is the single source of truth. The builder UI calls it to render
     * choices, and the checkout validator calls it again to verify what was
     * actually submitted.
     *
     * @return list<int>
     */
    public function allowedProductIds(int $giftBoxId): array
    {
        $categoryIds = array_column(
            $this->db->table('gift_box_categories')
                ->select('category_id')->where('gift_box_id', $giftBoxId)
                ->get()->getResultArray(),
            'category_id'
        );

        $base = $this->db->table('products')
            ->select('id')
            ->where('is_active', 1)
            ->where('is_giftbox_eligible', 1)
            ->where('deleted_at', null);

        if ($categoryIds !== []) {
            $base->whereIn('category_id', $categoryIds);
        }

        $ids = array_map('intval', array_column($base->get()->getResultArray(), 'id'));

        $pins = $this->db->table('gift_box_products')
            ->select('product_id, is_excluded')
            ->where('gift_box_id', $giftBoxId)
            ->get()->getResultArray();

        $excluded = [];

        foreach ($pins as $pin) {
            $productId = (int) $pin['product_id'];

            if ((int) $pin['is_excluded'] === 1) {
                $excluded[] = $productId;
            } else {
                $ids[] = $productId;
            }
        }

        return array_values(array_diff(array_unique($ids), $excluded));
    }

    /** @return array<int, array<string, mixed>> Per-product caps for a box. */
    public function productCaps(int $giftBoxId): array
    {
        $rows = $this->db->table('gift_box_products')
            ->select('product_id, max_quantity')
            ->where('gift_box_id', $giftBoxId)
            ->where('is_excluded', 0)
            ->where('max_quantity IS NOT NULL')
            ->get()->getResultArray();

        $caps = [];

        foreach ($rows as $row) {
            $caps[(int) $row['product_id']] = (int) $row['max_quantity'];
        }

        return $caps;
    }
}
