<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Attributes, seeded from the shop's own catalogues.
 *
 * The values here are the ones that actually appear in the German Silver,
 * Premium Collection and Drinkware PDFs — "Peacock" and "Elephant" are real
 * shapes Rasmein sells, not placeholders. A shop that opens the admin and finds
 * its own vocabulary already there has nothing to set up before it can work.
 *
 * Idempotent by code, so a later deploy never overwrites edits.
 */
class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $sets = [
            [
                'code' => 'colour', 'name' => 'Colour', 'input_type' => 'swatch',
                // Colour is the one thing a buyer of return gifts states a
                // preference about, so it is a chooser rather than a spec.
                'is_selectable' => 1, 'sort_order' => 10,
                'values' => [
                    ['Silver', '#C0C0C0'],
                    ['Antique Silver', '#8F8F8A'],
                    ['Gold', '#C6A15B'],
                    ['Rose Gold', '#B76E79'],
                    ['Copper', '#B87333'],
                    ['Brass', '#B5A642'],
                    ['Red', '#8E1B2E'],
                    ['Maroon', '#5E1F3D'],
                    ['Green', '#3F5E45'],
                    ['Blue', '#2E4A6B'],
                    ['Pink', '#D98AA0'],
                    ['Ivory', '#F3EADA'],
                    ['Black', '#241F27'],
                ],
            ],
            [
                'code' => 'size', 'name' => 'Size', 'input_type' => 'text',
                // A dimension is a fact about the piece, not a choice.
                'is_selectable' => 1, 'sort_order' => 20,
                'values' => [
                    ['4" x 4"', null], ['5" x 5"', null], ['6" x 6"', null],
                    ['7" x 7"', null], ['8" x 8"', null], ['9" x 12"', null],
                    ['12" x 12"', null], ['12" x 16"', null],
                    ['8" x 3.5" x 5"', null], ['6" x 6" x 4.5"', null],
                ],
            ],
            [
                'code' => 'shape', 'name' => 'Shape', 'input_type' => 'text',
                'is_selectable' => 1, 'sort_order' => 30,
                'values' => [
                    ['Peacock', null], ['Elephant', null], ['Lotus', null],
                    ['Round', null], ['Square', null], ['Oval', null],
                    ['Rectangular', null], ['Leaf', null],
                ],
            ],
            [
                'code' => 'finish', 'name' => 'Finish', 'input_type' => 'text',
                'is_selectable' => 1, 'sort_order' => 40,
                'values' => [
                    ['Polished', null], ['Matte', null], ['Antique', null],
                    ['Hammered', null], ['Enamelled', null], ['Mirror', null],
                ],
            ],
            [
                'code' => 'capacity', 'name' => 'Capacity', 'input_type' => 'text',
                'is_selectable' => 0, 'sort_order' => 50,
                'values' => [
                    ['250 ml', null], ['350 ml', null], ['500 ml', null],
                    ['750 ml', null], ['1 litre', null],
                ],
            ],
        ];

        $added = 0;

        foreach ($sets as $set) {
            $existing = $this->db->table('attributes')->where('code', $set['code'])->get()->getRowArray();

            if ($existing === null) {
                $this->db->table('attributes')->insert([
                    'code'          => $set['code'],
                    'name'          => $set['name'],
                    'input_type'    => $set['input_type'],
                    'is_selectable' => $set['is_selectable'],
                    'is_filterable' => 1,
                    'sort_order'    => $set['sort_order'],
                    'is_active'     => 1,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);

                $attributeId = (int) $this->db->insertID();
                $added++;
            } else {
                $attributeId = (int) $existing['id'];
            }

            foreach ($set['values'] as $i => [$label, $hex]) {
                // Values are added individually, so a shop that deleted one
                // does not have it silently restored on the next deploy — only
                // genuinely new ones appear.
                $has = $this->db->table('attribute_values')
                    ->where('attribute_id', $attributeId)
                    ->where('label', $label)
                    ->countAllResults();

                if ($has > 0) {
                    continue;
                }

                $this->db->table('attribute_values')->insert([
                    'attribute_id' => $attributeId,
                    'label'        => $label,
                    'swatch_hex'   => $hex,
                    'sort_order'   => $i * 10,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }

        echo '  Attributes: ' . $added . " new, values reconciled.\n";
    }
}
