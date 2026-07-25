<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Gift boxes, their allowed categories, and pricing rules.
 *
 * The Corporate Crate is deliberately pinned to `enquire_now` while the rest
 * inherit the site switch. That gives the build one live example of a
 * per-product journey override to test against, which is the requirement's
 * "one mode at a time, never both".
 */
class GiftBoxSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $categoryIds = [];

        foreach ($this->db->table('categories')->select('id, slug')->get()->getResultArray() as $row) {
            $categoryIds[$row['slug']] = (int) $row['id'];
        }

        $boxes = [
            [
                'name'        => 'The Petit Box',
                'slug'        => 'petit-box',
                'description' => 'Three compartments in a slim kraft box. For a thank-you, or a first hello.',
                'motif'       => 'stationery',
                'base_price'  => 250,
                'capacity'    => 3,
                'min_slots'   => 2,
                'size_label'  => 'Small',
                'theme'       => 'Everyday',
                'price_tier'  => 'Under 1500',
                'sale_mode'   => 'inherit',
                'featured'    => 1,
                'categories'  => ['dry-fruits-nuts', 'chocolates', 'teas-infusions', 'artisan-snacks'],
                'rules'       => [
                    ['flat_box_price', 250, null, null, null, 'Box & packing', 1],
                ],
            ],
            [
                'name'        => 'The Classic Tray',
                'slug'        => 'classic-tray',
                'description' => 'Six brass-ringed compartments in a rigid tray with a fabric lid. Our most-sent box.',
                'motif'       => 'ceramics',
                'base_price'  => 550,
                'capacity'    => 6,
                'min_slots'   => 3,
                'size_label'  => 'Medium',
                'theme'       => 'Festive',
                'price_tier'  => '1500 – 4000',
                'sale_mode'   => 'inherit',
                'featured'    => 1,
                'categories'  => ['dry-fruits-nuts', 'chocolates', 'teas-infusions', 'artisan-snacks', 'candles-fragrance'],
                'rules'       => [
                    ['flat_box_price', 550, null, null, null, 'Box & packing', 1],
                    // Fill it properly and the contents come down 5%.
                    ['slot_discount_percent', 5, 6, 6, null, 'Full tray — 5% off contents', 2],
                ],
            ],
            [
                'name'        => 'The Grand Hamper',
                'slug'        => 'grand-hamper',
                'description' => 'Nine compartments in a lined wooden crate. Room for ceramics and candles alongside the edibles.',
                'motif'       => 'candles',
                'base_price'  => 950,
                'capacity'    => 9,
                'min_slots'   => 4,
                'size_label'  => 'Large',
                'theme'       => 'Celebration',
                'price_tier'  => '4000 +',
                'sale_mode'   => 'inherit',
                'featured'    => 1,
                'categories'  => [], // every gift-box-eligible product
                'rules'       => [
                    ['flat_box_price', 950, null, null, null, 'Crate & packing', 1],
                    // Spend enough on contents and the crate is on us.
                    ['waive_box_price', 0, null, null, 6000, 'Crate free above ₹6,000', 2],
                ],
            ],
            [
                'name'        => 'Corporate Crate',
                'slug'        => 'corporate-crate',
                'description' => 'Twelve compartments, your branding on the sleeve, and a minimum of 25 units. Quoted per brief.',
                'motif'       => 'chocolate',
                'base_price'  => 1200,
                'capacity'    => 12,
                'min_slots'   => 6,
                'size_label'  => 'Bulk',
                'theme'       => 'Corporate',
                'price_tier'  => 'On request',
                // Pinned: bulk work is always quoted, whatever the site switch says.
                'sale_mode'   => 'enquire_now',
                'featured'    => 0,
                'categories'  => [],
                'rules'       => [
                    ['flat_box_price', 1200, null, null, null, 'Crate, sleeve & branding', 1],
                    ['percent_markup', 0, null, null, null, 'Quoted per brief', 2],
                ],
            ],
        ];

        $added = 0;

        foreach ($boxes as $index => $box) {
            if ($this->db->table('gift_boxes')->where('slug', $box['slug'])->countAllResults() > 0) {
                continue;
            }

            $this->db->table('gift_boxes')->insert([
                'name'                   => $box['name'],
                'slug'                   => $box['slug'],
                'description'            => $box['description'],
                'image'                  => 'assets/img/catalogue/' . $box['motif'] . '.svg',
                'base_price'             => $box['base_price'],
                'capacity_slots'         => $box['capacity'],
                'min_slots'              => $box['min_slots'],
                'size_label'             => $box['size_label'],
                'theme'                  => $box['theme'],
                'price_tier'             => $box['price_tier'],
                'sale_mode'              => $box['sale_mode'],
                'allow_gift_message'     => 1,
                'gift_message_max_chars' => 300,
                'allow_special_note'     => 1,
                'is_featured'            => $box['featured'],
                'sort_order'             => $index + 1,
                'is_active'              => 1,
                'meta_title'             => $box['name'] . ' — Build your own gift box | Rasmein',
                'meta_description'       => mb_substr($box['description'], 0, 155),
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);

            $boxId = (int) $this->db->insertID();

            foreach ($box['categories'] as $slug) {
                if (! isset($categoryIds[$slug])) {
                    continue;
                }

                $this->db->table('gift_box_categories')->insert([
                    'gift_box_id' => $boxId,
                    'category_id' => $categoryIds[$slug],
                    'created_at'  => $now,
                ]);
            }

            foreach ($box['rules'] as [$type, $value, $minSlots, $maxSlots, $minSubtotal, $label, $priority]) {
                $this->db->table('gift_box_pricing_rules')->insert([
                    'gift_box_id'  => $boxId,
                    'rule_type'    => $type,
                    'value'        => $value,
                    'min_slots'    => $minSlots,
                    'max_slots'    => $maxSlots,
                    'min_subtotal' => $minSubtotal,
                    'label'        => $label,
                    'priority'     => $priority,
                    'is_active'    => 1,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }

            $added++;
        }

        echo "  Gift boxes: {$added} added with categories and pricing rules.\n";
    }
}
