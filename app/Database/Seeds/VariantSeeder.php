<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Turn each product's attributes into the combinations it can be bought in.
 *
 * WHICH ATTRIBUTES MAKE A VARIANT
 *
 * Only the ones marked `is_selectable`. A size and a finish describe the piece;
 * a colour is a choice the buyer makes. Multiplying every attribute together
 * would produce a variant per size — which is not a thing anyone can order,
 * because the piece is that size.
 *
 * Products with a single selectable value get ONE variant. That is deliberate:
 * a uniform shape means the cart, the order and the product page never need a
 * "does this have variants" branch, and a second colour added later is an
 * insert rather than a migration.
 */
class VariantSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->table('variant_values')->truncate();
        $this->db->table('product_variants')->truncate();
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        // Which attributes the buyer actually chooses between.
        $selectable = array_column(
            $this->db->table('attributes')->select('code')->where('is_selectable', 1)->get()->getResultArray(),
            'code'
        );

        if ($selectable === []) {
            echo "  Variants: no selectable attributes — nothing to build.\n";

            return;
        }

        // Every product's values, in one query.
        $rows = $this->db->table('product_attributes pa')
            ->select('pa.product_id, av.id AS value_id, av.label, a.code', false)
            ->join('attribute_values av', 'av.id = pa.value_id')
            ->join('attributes a', 'a.id = av.attribute_id')
            ->orderBy('a.sort_order', 'ASC')
            ->orderBy('av.sort_order', 'ASC')
            ->get()->getResultArray();

        $byProduct = [];

        foreach ($rows as $row) {
            $byProduct[(int) $row['product_id']][(string) $row['code']][] = [
                'id' => (int) $row['value_id'], 'label' => (string) $row['label'],
            ];
        }

        $products = $this->db->table('products')->select('id, sku, name, price')->get()->getResultArray();

        $variants = [];
        $links    = [];
        $order    = 0;

        foreach ($products as $product) {
            $productId = (int) $product['id'];
            $owned     = $byProduct[$productId] ?? [];

            // The selectable groups this product actually has.
            $axes = [];

            foreach ($selectable as $code) {
                if (! empty($owned[$code])) {
                    $axes[$code] = $owned[$code];
                }
            }

            // Nothing to choose between: one plain variant, so every product
            // has the same shape.
            if ($axes === []) {
                $axes = ['_' => [['id' => 0, 'label' => 'Standard']]];
            }

            /*
             * Not every combination exists.
             *
             * A real catalogue is asymmetric: a piece may be offered large in
             * silver but only small in gold. Cross-multiplying blindly invents
             * stock nobody has, and — worse for testing — hides the whole
             * problem the selector exists to solve.
             *
             * The rule below is deterministic (seeded on the SKU, not random)
             * so the catalogue is identical on every machine and every rerun.
             */
            $combos = $this->combinations($axes);
            $keep   = [];

            foreach ($combos as $n => $combo) {
                // The first combination of every piece always exists, or a
                // product could end up with nothing to sell.
                if ($n === 0 || count($axes) < 2) {
                    $keep[] = $combo;

                    continue;
                }

                // Drop roughly a fifth of the rest, decided by the SKU so it
                // never changes between runs.
                if ((crc32($product['sku'] . $n) % 5) !== 0) {
                    $keep[] = $combo;
                }
            }

            foreach ($keep as $n => $combo) {
                $labels = array_column($combo, 'label');
                $key    = $this->slug(implode('-', $labels));

                /*
                 * A colour can carry a premium. Antique and hand-finished work
                 * costs more to produce, which is exactly how the catalogues
                 * price it — so the seed reflects that rather than making every
                 * variant identical and leaving the price path untested.
                 */
                $premium = 0;

                foreach ($labels as $label) {
                    if (stripos($label, 'antique') !== false) {
                        $premium += 200;
                    } elseif (stripos($label, 'gold') !== false) {
                        $premium += 350;
                    } elseif (stripos($label, 'copper') !== false) {
                        $premium += 150;
                    }
                }

                $variants[] = [
                    'product_id'  => $productId,
                    'sku'         => $product['sku'] . '-' . strtoupper(substr($key, 0, 12)) . '-' . ($n + 1),
                    'variant_key' => $key,
                    'label'       => implode(' · ', $labels),
                    // Null when there is no premium — so it follows the product.
                    'price'       => $premium > 0 ? (float) $product['price'] + $premium : null,
                    'stock_qty'   => $n === 0 ? 25 : 12,
                    'is_default'  => $n === 0 ? 1 : 0,
                    'is_active'   => 1,
                    'sort_order'  => $order += 10,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                    '_values'     => array_values(array_filter(array_column($combo, 'id'))),
                ];
            }
        }

        // Insert in batches — one statement per 500 rather than per row.
        foreach (array_chunk($variants, 500) as $chunk) {
            $this->db->table('product_variants')->insertBatch(array_map(
                static fn (array $v): array => array_diff_key($v, ['_values' => null]),
                $chunk
            ));
        }

        // Read the ids back by SKU; insertBatch does not return them.
        $ids = [];

        foreach ($this->db->table('product_variants')->select('id, sku')->get()->getResultArray() as $row) {
            $ids[$row['sku']] = (int) $row['id'];
        }

        foreach ($variants as $variant) {
            $variantId = $ids[$variant['sku']] ?? null;

            if ($variantId === null) {
                continue;
            }

            foreach ($variant['_values'] as $valueId) {
                $links[] = ['variant_id' => $variantId, 'value_id' => $valueId];
            }
        }

        foreach (array_chunk($links, 1000) as $chunk) {
            $this->db->table('variant_values')->insertBatch($chunk);
        }

        echo '  Variants: ' . count($variants) . ' across ' . count($products)
            . ' products, ' . count($links) . " value links.\n";
    }

    /**
     * The cartesian product of the axes.
     *
     * @param array<string, array<int, array<string, mixed>>> $axes
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function combinations(array $axes): array
    {
        $out = [[]];

        foreach ($axes as $values) {
            $next = [];

            foreach ($out as $partial) {
                foreach ($values as $value) {
                    $next[] = array_merge($partial, [$value]);
                }
            }

            $out = $next;
        }

        return $out;
    }

    private function slug(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/["\x27]/', '', $s) ?? $s;
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? $s;

        return trim($s, '-') ?: 'standard';
    }
}
