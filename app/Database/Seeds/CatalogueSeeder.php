<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Demo catalogue: categories, products, one image each, and collections.
 *
 * Realistic enough to design and test against — the prices, weights and slot
 * costs are the kind of values Rasmein will actually use, so capacity and
 * pricing behaviour can be judged properly before real stock is loaded.
 */
class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // --------------------------------------------------- categories
        $categories = [
            ['Dry Fruits & Nuts', 'dry-fruits-nuts', 'nuts', 'Almonds, pistachios, dates and figs — the backbone of any Indian hamper.', 1],
            ['Chocolates', 'chocolates', 'chocolate', 'Single-origin bars and filled bonbons from small Indian makers.', 1],
            ['Teas & Infusions', 'teas-infusions', 'tea', 'Darjeeling first flush, Nilgiri, and caffeine-free herbal blends.', 1],
            ['Artisan Snacks', 'artisan-snacks', 'snacks', 'Roasted makhana, baked namkeen, and savoury mixes worth sending.', 0],
            ['Candles & Fragrance', 'candles-fragrance', 'candles', 'Soy candles and reed diffusers in scents that suit a home, not a hotel.', 1],
            ['Ceramics & Serveware', 'ceramics-serveware', 'ceramics', 'Hand-thrown bowls, platters and cups from studios in Jaipur and Pondicherry.', 0],
            ['Bath & Body', 'bath-body', 'bath', 'Cold-pressed oils, salt scrubs and balms, kept simple.', 0],
            ['Stationery', 'stationery', 'stationery', 'Cotton-paper notebooks and letterpress greeting cards.', 0],
        ];

        $categoryIds = [];

        foreach ($categories as $index => [$name, $slug, $motif, $description, $featured]) {
            $existing = $this->db->table('categories')->where('slug', $slug)->get()->getRowArray();

            if ($existing !== null) {
                $categoryIds[$slug] = (int) $existing['id'];
                continue;
            }

            $this->db->table('categories')->insert([
                'name'             => $name,
                'slug'             => $slug,
                'description'      => $description,
                'image'            => 'assets/img/catalogue/' . $motif . '.svg',
                'is_featured'      => $featured,
                'sort_order'       => $index + 1,
                'is_active'        => 1,
                'meta_title'       => $name . ' — Rasmein',
                'meta_description' => $description,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            $categoryIds[$slug] = (int) $this->db->insertID();
        }

        echo '  Categories: ' . count($categoryIds) . " in place.\n";

        // ----------------------------------------------------- products
        // [sku, name, slug, category, price, compareAt, stock, unit, slots, featured, blurb]
        $products = [
            ['RSM-DF-001', 'Mamra Almonds', 'mamra-almonds', 'dry-fruits-nuts', 690, 790, 120, '250 g jar', 1, 1, 'Small, dense Iranian-variety almonds grown in Kashmir. Sweeter and oilier than a California almond.'],
            ['RSM-DF-002', 'Salted Pistachios', 'salted-pistachios', 'dry-fruits-nuts', 540, null, 90, '200 g jar', 1, 1, 'Roasted in-shell with rock salt. Nothing else added.'],
            ['RSM-DF-003', 'Medjool Dates', 'medjool-dates', 'dry-fruits-nuts', 480, 560, 70, '300 g box', 1, 0, 'Soft, caramel-heavy dates. Stone in, as they keep better that way.'],
            ['RSM-DF-004', 'Anjeer Figs', 'anjeer-figs', 'dry-fruits-nuts', 620, null, 55, '250 g jar', 1, 0, 'Sun-dried Afghan figs, unsulphured, so the colour is honest.'],
            ['RSM-DF-005', 'Walnut Halves', 'walnut-halves', 'dry-fruits-nuts', 580, null, 0, '200 g jar', 1, 0, 'Light-coloured Kashmiri halves, hand-shelled to keep them whole.'],

            ['RSM-CH-001', '72% Dark Chocolate', 'dark-chocolate-72', 'chocolates', 320, null, 200, '80 g bar', 1, 1, 'Single-origin Idukki cacao, conched for three days. Bitter, fruity finish.'],
            ['RSM-CH-002', 'Sea Salt & Caramel Bar', 'sea-salt-caramel-bar', 'chocolates', 350, 400, 160, '80 g bar', 1, 1, 'Milk chocolate over a soft caramel layer, finished with flaked salt.'],
            ['RSM-CH-003', 'Assorted Bonbons', 'assorted-bonbons', 'chocolates', 890, null, 45, 'box of 9', 2, 1, 'Nine filled bonbons: cardamom, coffee, rose, and a plain 70% ganache.'],
            ['RSM-CH-004', 'Cacao Nib Brittle', 'cacao-nib-brittle', 'chocolates', 290, null, 110, '120 g pack', 1, 0, 'Jaggery brittle studded with roasted cacao nibs and sesame.'],

            ['RSM-TE-001', 'Darjeeling First Flush', 'darjeeling-first-flush', 'teas-infusions', 750, 850, 60, '100 g tin', 1, 1, 'Spring pluck from a single Kurseong garden. Light, floral, no milk.'],
            ['RSM-TE-002', 'Nilgiri Frost Tea', 'nilgiri-frost-tea', 'teas-infusions', 640, null, 48, '100 g tin', 1, 0, 'Winter-picked and unusually sweet. Holds up to a splash of milk.'],
            ['RSM-TE-003', 'Tulsi & Ginger Infusion', 'tulsi-ginger-infusion', 'teas-infusions', 420, null, 130, '80 g tin', 1, 0, 'Caffeine-free. Holy basil, dried ginger, a little liquorice root.'],
            ['RSM-TE-004', 'Masala Chai Blend', 'masala-chai-blend', 'teas-infusions', 480, null, 140, '150 g tin', 1, 1, 'Assam base with cardamom, clove, cinnamon and pepper, ground fresh.'],

            ['RSM-SN-001', 'Roasted Makhana', 'roasted-makhana', 'artisan-snacks', 280, null, 180, '100 g pack', 1, 0, 'Bihar fox nuts roasted in ghee with black pepper and rock salt.'],
            ['RSM-SN-002', 'Baked Methi Namkeen', 'baked-methi-namkeen', 'artisan-snacks', 240, null, 200, '200 g pack', 1, 0, 'Baked, not fried. Fenugreek, ajwain and a lot of black pepper.'],
            ['RSM-SN-003', 'Peri Peri Cashews', 'peri-peri-cashews', 'artisan-snacks', 520, 590, 95, '150 g jar', 1, 1, 'W240 cashews tossed in a dry peri peri rub with lime.'],

            ['RSM-CA-001', 'Oudh & Amber Candle', 'oudh-amber-candle', 'candles-fragrance', 1250, null, 40, '200 g, 45 hrs', 2, 1, 'Soy wax in a reusable stoneware vessel. Warm, resinous, not sweet.'],
            ['RSM-CA-002', 'Vetiver & Lime Candle', 'vetiver-lime-candle', 'candles-fragrance', 1150, 1350, 35, '200 g, 45 hrs', 2, 0, 'Damp, grassy vetiver cut with lime peel. Good for a warm evening.'],
            ['RSM-CA-003', 'Sandalwood Reed Diffuser', 'sandalwood-reed-diffuser', 'candles-fragrance', 1490, null, 25, '150 ml', 2, 0, 'Mysore sandalwood with a little cedar. Lasts about ten weeks.'],

            ['RSM-CE-001', 'Stoneware Nut Bowl', 'stoneware-nut-bowl', 'ceramics-serveware', 850, null, 30, '12 cm', 2, 0, 'Hand-thrown in Jaipur, glazed in a matte oatmeal. Each one differs slightly.'],
            ['RSM-CE-002', 'Blue Pottery Platter', 'blue-pottery-platter', 'ceramics-serveware', 1650, 1850, 18, '26 cm', 3, 1, 'Jaipur blue pottery, hand-painted. Decorative — not for the dishwasher.'],
            ['RSM-CE-003', 'Kulhad Cup Set', 'kulhad-cup-set', 'ceramics-serveware', 720, null, 42, 'set of 4', 2, 0, 'Unglazed terracotta cups. Chai tastes different from clay, which is the point.'],

            ['RSM-BB-001', 'Cold-Pressed Coconut Balm', 'cold-pressed-coconut-balm', 'bath-body', 460, null, 85, '100 g tin', 1, 0, 'Two ingredients: virgin coconut oil and beeswax. For dry hands in winter.'],
            ['RSM-BB-002', 'Himalayan Salt Scrub', 'himalayan-salt-scrub', 'bath-body', 590, null, 60, '250 g jar', 2, 0, 'Pink salt, cold-pressed sesame oil, and a little rosemary.'],

            ['RSM-ST-001', 'Cotton Paper Notebook', 'cotton-paper-notebook', 'stationery', 550, null, 75, 'A5, 120 pages', 1, 0, 'Handmade cotton-rag paper, thread-bound so it opens flat.'],
            ['RSM-ST-002', 'Letterpress Card Set', 'letterpress-card-set', 'stationery', 380, null, 100, 'set of 6', 1, 0, 'Blank inside, printed on a hand-fed press. Envelopes included.'],
        ];

        $inserted = 0;

        foreach ($products as $index => [$sku, $name, $slug, $categorySlug, $price, $compareAt, $stock, $unit, $slots, $featured, $blurb]) {
            if ($this->db->table('products')->where('sku', $sku)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('products')->insert([
                'category_id'         => $categoryIds[$categorySlug] ?? null,
                'sku'                 => $sku,
                'name'                => $name,
                'slug'                => $slug,
                'short_description'   => $blurb,
                'description'         => $blurb . ' Packed in-house and sealed the day it ships.',
                'price'               => $price,
                'compare_at_price'    => $compareAt,
                'stock_qty'           => $stock,
                'low_stock_threshold' => 10,
                'track_inventory'     => 1,
                'unit_label'          => $unit,
                'sale_mode'           => 'inherit',
                'is_giftbox_eligible' => 1,
                'giftbox_slots'       => $slots,
                'is_featured'         => $featured,
                'is_active'           => 1,
                'sort_order'          => $index + 1,
                'meta_title'          => $name . ' — Rasmein',
                'meta_description'    => mb_substr($blurb, 0, 155),
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            $productId = (int) $this->db->insertID();

            // One stand-in image per product, tinted by its category.
            $motif = array_column($categories, 2, 1)[$categorySlug] ?? 'nuts';

            $this->db->table('product_images')->insert([
                'product_id' => $productId,
                'path'       => 'assets/img/catalogue/' . $motif . '.svg',
                'alt_text'   => $name,
                'is_primary' => 1,
                'sort_order' => 1,
                'created_at' => $now,
            ]);

            $inserted++;
        }

        echo "  Products: {$inserted} added (" . count($products) . " defined).\n";

        // -------------------------------------------------- collections
        $collections = [
            ['Diwali 2026', 'diwali-2026', 'Boxes built around sweets, dry fruits and light.', 'chocolate', 1, ['mamra-almonds', 'assorted-bonbons', 'oudh-amber-candle', 'medjool-dates']],
            ['For a New Home', 'for-a-new-home', 'Something for the kitchen, something for the shelf.', 'ceramics', 1, ['blue-pottery-platter', 'kulhad-cup-set', 'sandalwood-reed-diffuser', 'masala-chai-blend']],
            ['The Tea Drinker', 'the-tea-drinker', 'Four teas and a cup worth drinking them from.', 'tea', 1, ['darjeeling-first-flush', 'nilgiri-frost-tea', 'masala-chai-blend', 'kulhad-cup-set']],
        ];

        $added = 0;

        foreach ($collections as $index => [$name, $slug, $description, $motif, $featured, $productSlugs]) {
            if ($this->db->table('collections')->where('slug', $slug)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('collections')->insert([
                'name'        => $name,
                'slug'        => $slug,
                'description' => $description,
                'image'       => 'assets/img/catalogue/' . $motif . '.svg',
                'is_featured' => $featured,
                'sort_order'  => $index + 1,
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $collectionId = (int) $this->db->insertID();

            foreach ($productSlugs as $position => $productSlug) {
                $product = $this->db->table('products')->select('id')->where('slug', $productSlug)->get()->getRowArray();

                if ($product === null) {
                    continue;
                }

                $this->db->table('collection_products')->insert([
                    'collection_id' => $collectionId,
                    'product_id'    => (int) $product['id'],
                    'sort_order'    => $position + 1,
                    'created_at'    => $now,
                ]);
            }

            $added++;
        }

        echo "  Collections: {$added} added.\n";
    }
}
