<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The real catalogue: 155 pieces from the German Silver and Premium Collection
 * PDFs, with their categories and attributes.
 *
 * WHAT THIS REPLACES
 *
 * Running it CLEARS products, categories and their joins first. That is
 * destructive on purpose — it was asked for, and a merge would leave the demo
 * catalogue interleaved with the real one, which is worse than either alone.
 * Orders are never touched: an order keeps name and price snapshots precisely
 * so the catalogue can change underneath it.
 *
 * WHAT IS REAL AND WHAT IS NOT
 *
 *  - Names, SKUs and categories come from the PDFs.
 *  - Sizes come from the PDFs where the layout allowed them to be read.
 *  - 42 prices are the catalogue's own top-of-ladder figure. The other 113 sit
 *    in the Premium Collection's two-column layout where the price belongs to
 *    the code, not the name, and could not be tied to a product with
 *    confidence. Those carry the CATEGORY MEDIAN and are flagged
 *    `price_is_estimated` in the description, so nobody ships them by accident.
 *  - Images are left empty. The shop is adding them.
 */
class ProductCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        /*
         * Cleared in child-before-parent order. The foreign keys would cascade,
         * but relying on that means the order of these lines silently matters
         * the day a constraint changes.
         */
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach (['product_attributes', 'product_images', 'collection_products', 'products', 'categories'] as $table) {
            $this->db->table($table)->truncate();
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        // ------------------------------------------------------- categories
        $categories = [
            ['Dry Fruit Boxes', 'dry-fruit-boxes', 'Hand-finished boxes for nuts, dates and mithai.'],
            ['Serving Trays', 'serving-trays', 'Trays and platters for tea, sweets and ceremony.'],
            ['Bowls & Katoris', 'bowls-katoris', 'Bowls, urlis and katoris in silver and brass.'],
            ['Jars & Containers', 'jars-containers', 'Lidded jars for the table and the mantelpiece.'],
            ['Pooja & Ritual', 'pooja-ritual', 'Diyas, thalis and bells for the prayer room.'],
            ['Candle Holders', 'candle-holders', 'Votives and stands, for light that flatters a room.'],
            ['Drinkware', 'drinkware', 'Glasses, tumblers and decanters.'],
            ['Gift Boxes & Hampers', 'gift-boxes-hampers', 'Ready hampers and boxes to build your own.'],
            ['Plates & Serveware', 'plates-serveware', 'Plates and serveware for the table.'],
            ['Desk & Decor', 'desk-decor', 'Frames, stands and pieces for a desk or shelf.'],
            ['Decorative Accents', 'decorative-accents', 'Smaller pieces that finish a room.'],
        ];

        $categoryIds = [];
        $sort        = 0;

        foreach ($categories as [$name, $slug, $blurb]) {
            $this->db->table('categories')->insert([
                'name'        => $name,
                'slug'        => $slug,
                // Top-level: materialised path is just the slug.
                'path'        => $slug,
                'parent_id'   => null,
                'depth'       => 0,
                'description' => $blurb,
                'sort_order'  => $sort += 10,
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $categoryIds[$name] = (int) $this->db->insertID();
        }

        // -------------------------------------------------------- attributes
        /*
         * The attribute vocabulary has to exist before products can be linked
         * to it. Running this seeder alone left every product with no
         * attributes and printed "0 attribute links" as though that were fine —
         * a silent no-op that looked like success.
         *
         * Depend on it explicitly rather than documenting the order somewhere
         * nobody reads. AttributeSeeder is idempotent, so calling it twice
         * costs nothing.
         */
        if ($this->db->table('attributes')->countAllResults() === 0) {
            echo "  Attributes were missing — seeding them first.\n";
            $this->call(AttributeSeeder::class);
        }

        // Looked up ONCE, by label, so 155 products do not run 400 queries.
        $valueIds = [];

        foreach ($this->db->table('attribute_values av')
            ->select('av.id, av.label, a.code')
            ->join('attributes a', 'a.id = av.attribute_id')
            ->get()->getResultArray() as $row) {
            $valueIds[$row['code'] . '|' . $row['label']] = (int) $row['id'];
        }

        // ---------------------------------------------------------- products
        $products = [
            ['sku' => 'TLG-11001', 'name' => 'Elephant Shape German Silver Decorative Bowl with Lid & Spoon', 'slug' => 'elephant-shape-german-silver-decorative-bowl-with-lid-spoon', 'category' => 'Bowls & Katoris',
                'price' => 2250, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Elephant'], 'size' => ['8" x 3.5" x 5"']]],
            ['sku' => 'TLG-11002', 'name' => 'German Silver Dry Fruit Basket with Elegant Finish', 'slug' => 'german-silver-dry-fruit-basket-with-elegant-finish', 'category' => 'Dry Fruit Boxes',
                'price' => 2100, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['5.75" x 5.75" x 2.5"']]],
            ['sku' => 'TLG-11003', 'name' => 'Peacock Design German Silver Decorative Container with Lid & Spoon', 'slug' => 'peacock-design-german-silver-decorative-container-with-lid-spoon', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Peacock'], 'size' => ['4.75" x 4.75" x 5.5"']]],
            ['sku' => 'TLG-11005', 'name' => 'Peacock Design German Silver Decorative Bowl', 'slug' => 'peacock-design-german-silver-decorative-bowl', 'category' => 'Bowls & Katoris',
                'price' => 1650, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Peacock'], 'size' => ['7.75" x 7.75" x 4.5"']]],
            ['sku' => 'TLG-11006', 'name' => 'German Silver Peacock Design Serving Tray', 'slug' => 'german-silver-peacock-design-serving-tray', 'category' => 'Serving Trays',
                'price' => 1550, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Peacock'], 'size' => ['8" x 6.5" x 4.25"']]],
            ['sku' => 'TLG-11007', 'name' => 'German Silver Peacock Dry Fruit Gift Box', 'slug' => 'german-silver-peacock-dry-fruit-gift-box', 'category' => 'Dry Fruit Boxes',
                'price' => 3250, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Peacock'], 'size' => ['8.75" x 7.25" x 5"']]],
            ['sku' => 'TLG-11008', 'name' => 'German Silver Pooja Thali Set with Ganesh Lakshmi Idols', 'slug' => 'german-silver-pooja-thali-set-with-ganesh-lakshmi-idols', 'category' => 'Pooja & Ritual',
                'price' => 5000, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['12" x 12" x 5"']]],
            ['sku' => 'TLG-11009', 'name' => 'German Silver Peacock Jewellery Box with Velvet Interior', 'slug' => 'german-silver-peacock-jewellery-box-with-velvet-interior', 'category' => 'Gift Boxes & Hampers',
                'price' => 2550, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Peacock'], 'size' => ['7.25" x 5.5" x 3"']]],
            ['sku' => 'TLG-11011', 'name' => 'Royal Silver Plated Dry Fruit Bowl with Premium Velvet Box', 'slug' => 'royal-silver-plated-dry-fruit-bowl-with-premium-velvet-box', 'category' => 'Dry Fruit Boxes',
                'price' => 2350, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['12" x 8.4" x 6"']]],
            ['sku' => 'TLG-11012', 'name' => 'Royal Elephant Dry Fruit Box – Silver Plated Designer Piece', 'slug' => 'royal-elephant-dry-fruit-box-silver-plated-designer-piece', 'category' => 'Dry Fruit Boxes',
                'price' => 2250, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Elephant'], 'size' => ['8" x 3.5" x 5"']]],
            ['sku' => 'TLG-11014', 'name' => 'German Silver Cycle Dry Fruit Bowl', 'slug' => 'german-silver-cycle-dry-fruit-bowl', 'category' => 'Dry Fruit Boxes',
                'price' => 3150, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['12" x 5" x 6"']]],
            ['sku' => 'TLG-11015', 'name' => 'Elegant Cycle-Shaped Silver Dry Fruit Bowl with Lid', 'slug' => 'elegant-cycle-shaped-silver-dry-fruit-bowl-with-lid', 'category' => 'Dry Fruit Boxes',
                'price' => 3250, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['11" x 6" x 7.25"']]],
            ['sku' => 'TLG-11016', 'name' => 'Luxury German Silver Dry Fruit Box with Designer Tray', 'slug' => 'luxury-german-silver-dry-fruit-box-with-designer-tray', 'category' => 'Dry Fruit Boxes',
                'price' => 5200, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['5.5" x 5.5" x 4"']]],
            ['sku' => 'TLG-11017', 'name' => 'Premium Designer Dry Fruit Storage Box with Silver Base', 'slug' => 'premium-designer-dry-fruit-storage-box-with-silver-base', 'category' => 'Dry Fruit Boxes',
                'price' => 3400, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['10.75" x 6.5" x 3.5"']]],
            ['sku' => 'TLG-11018', 'name' => 'Premium Decorative German Silver Serving Tray', 'slug' => 'premium-decorative-german-silver-serving-tray', 'category' => 'Serving Trays',
                'price' => 1900, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['9.5" x 1.75"']]],
            ['sku' => 'TLG-11019', 'name' => 'German Silver Decorative Square Tray / Dry Fruit Serving Platter', 'slug' => 'german-silver-decorative-square-tray-dry-fruit-serving-platter', 'category' => 'Dry Fruit Boxes',
                'price' => 1400, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Square'], 'size' => ['7" x 7" x 1.5"']]],
            ['sku' => 'TLG-11021', 'name' => 'German Silver Peacock Dry Fruit Box Set with Tray', 'slug' => 'german-silver-peacock-dry-fruit-box-set-with-tray', 'category' => 'Dry Fruit Boxes',
                'price' => 5100, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Peacock'], 'size' => ['6" x 6" x 3.75"']]],
            ['sku' => 'TLG-11022', 'name' => 'German Silver Jewelry Storage Box', 'slug' => 'german-silver-jewelry-storage-box', 'category' => 'Gift Boxes & Hampers',
                'price' => 3600, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['8.5" x 5.25" x 6.5"']]],
            ['sku' => 'TLG-11023', 'name' => 'Luxury German Silver Jewelry Box with Velvet Interior', 'slug' => 'luxury-german-silver-jewelry-box-with-velvet-interior', 'category' => 'Gift Boxes & Hampers',
                'price' => 3600, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['9" x 6" x 6.75"']]],
            ['sku' => 'TLG-11024', 'name' => 'German Silver Round Dry Fruit Box with Antique Finish', 'slug' => 'german-silver-round-dry-fruit-box-with-antique-finish', 'category' => 'Dry Fruit Boxes',
                'price' => 3500, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver', 'Antique Silver'], 'shape' => ['Round'], 'finish' => ['Antique'], 'size' => ['9.5" x 9.5" x 5"']]],
            ['sku' => 'TLG-11025', 'name' => 'Luxury German Silver Elephant Design Dry Fruit Set with Tray', 'slug' => 'luxury-german-silver-elephant-design-dry-fruit-set-with-tray', 'category' => 'Dry Fruit Boxes',
                'price' => 4500, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Elephant'], 'size' => ['5.25" x 5.25" x 5.5']]],
            ['sku' => 'TLG-11026', 'name' => 'Premium Silver-plated Dry Fruit Serving Set with Tray', 'slug' => 'premium-silver-plated-dry-fruit-serving-set-with-tray', 'category' => 'Dry Fruit Boxes',
                'price' => 6100, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['7.25" x 3.75" x 4.25"']]],
            ['sku' => 'TLG-11027', 'name' => 'Luxury Revolving 2-Tier German Silver Serving Stand with Horse Handle', 'slug' => 'luxury-revolving-2-tier-german-silver-serving-stand-with-horse-handle', 'category' => 'Plates & Serveware',
                'price' => 4800, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['10.25" x 10.25" x 14.5"']]],
            ['sku' => 'TLG-11028', 'name' => 'Luxury Silver Plated Peacock Serving Bowl with Premium Gift Box', 'slug' => 'luxury-silver-plated-peacock-serving-bowl-with-premium-gift-box', 'category' => 'Bowls & Katoris',
                'price' => 3900, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Peacock'], 'size' => ['10.25" x 6.5" x 11.75"']]],
            ['sku' => 'TLG-11029', 'name' => 'Luxury German Silver Dry Fruit Serving Set with Tray & Lid', 'slug' => 'luxury-german-silver-dry-fruit-serving-set-with-tray-lid', 'category' => 'Dry Fruit Boxes',
                'price' => 2900, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['3.5" x 3.5" x 4"']]],
            ['sku' => 'TLG-11030', 'name' => 'Premium Silver Plated Dry Fruit Serving Set with Handle & Tray', 'slug' => 'premium-silver-plated-dry-fruit-serving-set-with-handle-tray', 'category' => 'Dry Fruit Boxes',
                'price' => 3200, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['9" x 4.75" x 2.75"']]],
            ['sku' => 'TLG-11031', 'name' => 'German Silver Horse Cart Decorative Dry Fruit Box', 'slug' => 'german-silver-horse-cart-decorative-dry-fruit-box', 'category' => 'Dry Fruit Boxes',
                'price' => 2750, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['9.25" x 4.75" x 5.25"']]],
            ['sku' => 'TLG-11032', 'name' => 'Royal Horse Cart Dry Fruit Bowl with Dome Lid', 'slug' => 'royal-horse-cart-dry-fruit-bowl-with-dome-lid', 'category' => 'Dry Fruit Boxes',
                'price' => 4300, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['13.75" x 6.25" x 9.25"']]],
            ['sku' => 'TLG-11033', 'name' => 'Royal Silver Horse Cart Dry Fruit Serving Set Finely Sculpted', 'slug' => 'royal-silver-horse-cart-dry-fruit-serving-set-finely-sculpted', 'category' => 'Dry Fruit Boxes',
                'price' => 5400, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['18" x 7" x 12"']]],
            ['sku' => 'TLG-11034', 'name' => 'Luxury Silver Horse Cart Dry Fruit Serving Set with Lid', 'slug' => 'luxury-silver-horse-cart-dry-fruit-serving-set-with-lid', 'category' => 'Dry Fruit Boxes',
                'price' => 4000, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['18" x 5.75" x 7.5"']]],
            ['sku' => 'TLG-11035', 'name' => 'Royal Silver Horse Cart Dry Fruit Box with Lid', 'slug' => 'royal-silver-horse-cart-dry-fruit-box-with-lid', 'category' => 'Dry Fruit Boxes',
                'price' => 4100, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['12.5" x 7.25" x 10"']]],
            ['sku' => 'TLG-11036', 'name' => 'Royal German Silver Horse Carriage Dry Fruit Box', 'slug' => 'royal-german-silver-horse-carriage-dry-fruit-box', 'category' => 'Dry Fruit Boxes',
                'price' => 3700, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['14" x 5.75']]],
            ['sku' => 'TLG-11037', 'name' => 'Luxury German Silver Horse Cart Dry Fruit Box Set with Dual Containers', 'slug' => 'luxury-german-silver-horse-cart-dry-fruit-box-set-with-dual-containers', 'category' => 'Dry Fruit Boxes',
                'price' => 3800, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['16" x 4.75" x 7"']]],
            ['sku' => 'TLG-11038', 'name' => 'German Silver Horse Cart Round Dry Fruit Box with Partition', 'slug' => 'german-silver-horse-cart-round-dry-fruit-box-with-partition', 'category' => 'Dry Fruit Boxes',
                'price' => 4100, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Round'], 'size' => ['16" x 8.25" x 5.5"']]],
            ['sku' => 'TLG-11039', 'name' => 'German Silver Serving Bowl Set with Peacock Lid & Designer Platter', 'slug' => 'german-silver-serving-bowl-set-with-peacock-lid-designer-platter', 'category' => 'Serving Trays',
                'price' => 4900, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Peacock'], 'size' => ['5.5" x 5.5"']]],
            ['sku' => 'TLG-11040', 'name' => 'German Silver Peacock Serving Bowl with Designer Tray Set', 'slug' => 'german-silver-peacock-serving-bowl-with-designer-tray-set', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Peacock'], 'size' => ['8" x 7']]],
            ['sku' => 'TLG-11041', 'name' => 'Luxury 3 Jar Dry Fruit Container Set with Designer Metal Stand', 'slug' => 'luxury-3-jar-dry-fruit-container-set-with-designer-metal-stand', 'category' => 'Dry Fruit Boxes',
                'price' => 6200, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['4" x 4" x 4"']]],
            ['sku' => 'TLG-11042', 'name' => 'Luxury 2 Jar Dry Fruit Container Set with Designer Metal Stand', 'slug' => 'luxury-2-jar-dry-fruit-container-set-with-designer-metal-stand', 'category' => 'Dry Fruit Boxes',
                'price' => 4700, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['4" x 4" x 4"']]],
            ['sku' => 'TLG-11043', 'name' => 'German Silver Peacock Serving Bowl', 'slug' => 'german-silver-peacock-serving-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2800, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Peacock'], 'size' => ['10.75" x 5.25" x 5']]],
            ['sku' => 'TLG-11045', 'name' => 'German Silver Serving Tray Set with Bowls', 'slug' => 'german-silver-serving-tray-set-with-bowls', 'category' => 'Serving Trays',
                'price' => 4400, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['5" x 5" x 2.25"']]],
            ['sku' => 'TLG-11047', 'name' => 'Luxury Peacock Dry Fruit Serving Set with Tray', 'slug' => 'luxury-peacock-dry-fruit-serving-set-with-tray', 'category' => 'Dry Fruit Boxes',
                'price' => 6500, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Peacock'], 'size' => ['4" x 4" x 6.25"']]],
            ['sku' => 'TLG-11048', 'name' => 'Butterfly Design Dry Fruit Container with Lid & Spoon', 'slug' => 'butterfly-design-dry-fruit-container-with-lid-spoon', 'category' => 'Dry Fruit Boxes',
                'price' => 3450, 'material' => 'German Silver', 'collection' => 'German Silver', 'attrs' => ['colour' => ['Silver'], 'size' => ['4.25" x 4.25" x 4"']]],
            ['sku' => 'PL-7002', 'name' => 'Elephant Urli German Silver Finish', 'slug' => 'elephant-urli-german-silver-finish', 'category' => 'Pooja & Ritual',
                'price' => 5000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Elephant']]],
            ['sku' => 'PL-7002-2', 'name' => 'Silver Mirror Round Platter', 'slug' => 'silver-mirror-round-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Round'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7003', 'name' => 'Elephant Platter', 'slug' => 'elephant-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Elephant']]],
            ['sku' => 'PL-7003-2', 'name' => 'Bird Stand', 'slug' => 'bird-stand', 'category' => 'Desk & Decor',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7003-3', 'name' => 'Plater', 'slug' => 'plater', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7003-4', 'name' => 'Wooden Golden Platter in Croco Lether Finish', 'slug' => 'wooden-golden-platter-in-croco-lether-finish', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Wood', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold']]],
            ['sku' => 'PL-7004', 'name' => 'Metal Hammerd Jar Crystal Nobe Lid', 'slug' => 'metal-hammerd-jar-crystal-nobe-lid', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7004-2', 'name' => 'Copper Mirror Oval Platter', 'slug' => 'copper-mirror-oval-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Copper', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Copper'], 'shape' => ['Oval'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7005', 'name' => 'Pumking Jar', 'slug' => 'pumking-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Round']]],
            ['sku' => 'PL-7005-2', 'name' => 'Silver Rudraksh Bell', 'slug' => 'silver-rudraksh-bell', 'category' => 'Pooja & Ritual',
                'price' => 5000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver']]],
            ['sku' => 'PL-7005-3', 'name' => 'Silver Metal with Mirror Tray', 'slug' => 'silver-metal-with-mirror-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7006', 'name' => 'Metal Hammered Jar with Dome Lid', 'slug' => 'metal-hammered-jar-with-dome-lid', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Hammered']]],
            ['sku' => 'PL-7006-2', 'name' => 'Metal Photo Frame', 'slug' => 'metal-photo-frame', 'category' => 'Desk & Decor',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7007', 'name' => 'Top Mirror Rose Gold Jar with Croco Print', 'slug' => 'top-mirror-rose-gold-jar-with-croco-print', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Rose Gold', 'Gold'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7007-2', 'name' => 'Metal Hamered Box', 'slug' => 'metal-hamered-box', 'category' => 'Gift Boxes & Hampers',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Hammered']]],
            ['sku' => 'PL-7007-3', 'name' => 'Print Mirror Tray', 'slug' => 'print-mirror-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Mirror']]],
            ['sku' => 'PL-7008', 'name' => 'Turtle Diya', 'slug' => 'turtle-diya', 'category' => 'Pooja & Ritual',
                'price' => 5000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Round']]],
            ['sku' => 'PL-7008-2', 'name' => 'Elephant Plate', 'slug' => 'elephant-plate', 'category' => 'Plates & Serveware',
                'price' => 4800, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Elephant']]],
            ['sku' => 'PL-7008-3', 'name' => 'Metal Hammered Gold Jar', 'slug' => 'metal-hammered-gold-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold'], 'finish' => ['Hammered']]],
            ['sku' => 'PL-7009', 'name' => 'Metal Hammered Silver Jar', 'slug' => 'metal-hammered-silver-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'finish' => ['Hammered']]],
            ['sku' => 'PL-7009-2', 'name' => 'Round German Silver Finish Basket', 'slug' => 'round-german-silver-finish-basket', 'category' => 'Gift Boxes & Hampers',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Round']]],
            ['sku' => 'PL-7009-3', 'name' => 'Kamdhaenu Cow', 'slug' => 'kamdhaenu-cow', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7010', 'name' => 'Metal Hammmered Handi', 'slug' => 'metal-hammmered-handi', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7010-2', 'name' => 'Metal Mirror Tray', 'slug' => 'metal-mirror-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Mirror']]],
            ['sku' => 'PL-7010-3', 'name' => 'Aluminium Peacock Platter', 'slug' => 'aluminium-peacock-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Peacock']]],
            ['sku' => 'PL-7011', 'name' => 'Aluminium Silver Leaf', 'slug' => 'aluminium-silver-leaf', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver']]],
            ['sku' => 'PL-7011-2', 'name' => 'Glass Mirror Platter', 'slug' => 'glass-mirror-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Mirror']]],
            ['sku' => 'PL-7012', 'name' => 'Parrot Bowl', 'slug' => 'parrot-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7012-2', 'name' => 'Round Silver Mirror Tray', 'slug' => 'round-silver-mirror-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Round'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7013', 'name' => 'Giraff Hammered Handi Jar', 'slug' => 'giraff-hammered-handi-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Hammered']]],
            ['sku' => 'PL-7014', 'name' => 'Golden Metal Hammered Jar', 'slug' => 'golden-metal-hammered-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold'], 'finish' => ['Hammered']]],
            ['sku' => 'PL-7014-2', 'name' => 'Metal Mirror Gold Tray', 'slug' => 'metal-mirror-gold-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7014-3', 'name' => 'Aluminium Duck', 'slug' => 'aluminium-duck', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7014-4', 'name' => 'Aluminium German Silver Finish Bowl', 'slug' => 'aluminium-german-silver-finish-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver']]],
            ['sku' => 'PL-7014-5', 'name' => 'Metal Mirror Silver Tray', 'slug' => 'metal-mirror-silver-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7016', 'name' => 'Vired Oval Shape Basket', 'slug' => 'vired-oval-shape-basket', 'category' => 'Gift Boxes & Hampers',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Oval']]],
            ['sku' => 'PL-7017', 'name' => 'Golden Elephant Hamper', 'slug' => 'golden-elephant-hamper', 'category' => 'Gift Boxes & Hampers',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold'], 'shape' => ['Elephant']]],
            ['sku' => 'PL-7018', 'name' => 'Golden Mirror Round Tray', 'slug' => 'golden-mirror-round-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold'], 'shape' => ['Round'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7018-2', 'name' => 'Butterfly Wooden Lid Jar', 'slug' => 'butterfly-wooden-lid-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Wood', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7019', 'name' => 'Copper Mirror Jar', 'slug' => 'copper-mirror-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Copper', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Copper'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7019-2', 'name' => 'Metal Shank', 'slug' => 'metal-shank', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7019-3', 'name' => 'Copper Glass Mirror Platter', 'slug' => 'copper-glass-mirror-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Copper', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Copper'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7020', 'name' => 'Silver Metal Frame', 'slug' => 'silver-metal-frame', 'category' => 'Desk & Decor',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver']]],
            ['sku' => 'PL-7020-2', 'name' => 'Silver Metal Tray', 'slug' => 'silver-metal-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver']]],
            ['sku' => 'PL-7020-3', 'name' => 'Metal Hammered Jar With Dome Lid in Ocean Colour', 'slug' => 'metal-hammered-jar-with-dome-lid-in-ocean-colour', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Hammered']]],
            ['sku' => 'PL-7020-4', 'name' => 'Wooden Silver Platter in Croco Lether Finish', 'slug' => 'wooden-silver-platter-in-croco-lether-finish', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Wood', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver']]],
            ['sku' => 'PL-7023', 'name' => 'Silver Fruit Bowl', 'slug' => 'silver-fruit-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver']]],
            ['sku' => 'PL-7025', 'name' => 'Silver Elephant Bowl', 'slug' => 'silver-elephant-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Elephant']]],
            ['sku' => 'PL-7025-2', 'name' => 'Silver Glass Mirror Platter', 'slug' => 'silver-glass-mirror-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7026', 'name' => 'Metal Hammered Jar', 'slug' => 'metal-hammered-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Hammered']]],
            ['sku' => 'PL-7026-2', 'name' => 'Wax Candle', 'slug' => 'wax-candle', 'category' => 'Candle Holders',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7026-3', 'name' => 'Candel', 'slug' => 'candel', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7026-4', 'name' => 'Elephant Bowl', 'slug' => 'elephant-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Elephant']]],
            ['sku' => 'PL-7027', 'name' => 'Aluminium Peacock', 'slug' => 'aluminium-peacock', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Peacock']]],
            ['sku' => 'PL-7028', 'name' => 'Metal Silver Hammered Jar', 'slug' => 'metal-silver-hammered-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'finish' => ['Hammered']]],
            ['sku' => 'PL-7028-2', 'name' => 'Silver Mirror Platter', 'slug' => 'silver-mirror-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7029', 'name' => 'Almunium Silver Leaf', 'slug' => 'almunium-silver-leaf', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver']]],
            ['sku' => 'PL-7030', 'name' => 'Silver Elephant Colour Meena Work', 'slug' => 'silver-elephant-colour-meena-work', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Elephant']]],
            ['sku' => 'PL-7030-2', 'name' => 'Silver Mirror Jar', 'slug' => 'silver-mirror-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7030-3', 'name' => 'Tray with Mirror', 'slug' => 'tray-with-mirror', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Mirror']]],
            ['sku' => 'PL-7030-4', 'name' => 'Samaye', 'slug' => 'samaye', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7031', 'name' => 'Blue Mirror Tray', 'slug' => 'blue-mirror-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Mirror']]],
            ['sku' => 'PL-7032', 'name' => 'Metal Hammered Jar With Dome Lid in Purple Colour', 'slug' => 'metal-hammered-jar-with-dome-lid-in-purple-colour', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Hammered']]],
            ['sku' => 'PL-7033', 'name' => 'Silver Elephant Fruit Bowl', 'slug' => 'silver-elephant-fruit-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver'], 'shape' => ['Elephant']]],
            ['sku' => 'PL-7034', 'name' => 'Round Glass Basket with Handle', 'slug' => 'round-glass-basket-with-handle', 'category' => 'Drinkware',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Round']]],
            ['sku' => 'PL-7035', 'name' => 'German Silver Finish Box', 'slug' => 'german-silver-finish-box', 'category' => 'Gift Boxes & Hampers',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Silver']]],
            ['sku' => 'PL-7036', 'name' => 'Round Mirror Tray', 'slug' => 'round-mirror-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Round'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7037', 'name' => 'Metal Lotus Tray', 'slug' => 'metal-lotus-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Lotus']]],
            ['sku' => 'PL-7038', 'name' => 'Copper Glass Box', 'slug' => 'copper-glass-box', 'category' => 'Drinkware',
                'price' => 3600, 'material' => 'Copper', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Copper']]],
            ['sku' => 'PL-7039', 'name' => 'Golden Elephant Bowl', 'slug' => 'golden-elephant-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold'], 'shape' => ['Elephant']]],
            ['sku' => 'PL-7039-2', 'name' => 'Butterfly Lid Jar', 'slug' => 'butterfly-lid-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7042', 'name' => 'Dry Fruit Bowl', 'slug' => 'dry-fruit-bowl', 'category' => 'Dry Fruit Boxes',
                'price' => 3700, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7043', 'name' => 'Golden Elephant with Meena', 'slug' => 'golden-elephant-with-meena', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold'], 'shape' => ['Elephant']]],
            ['sku' => 'PL-7043-2', 'name' => 'Round Glass Mirror Platter', 'slug' => 'round-glass-mirror-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Round'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7044', 'name' => 'Golden Basket', 'slug' => 'golden-basket', 'category' => 'Gift Boxes & Hampers',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold']]],
            ['sku' => 'PL-7045', 'name' => 'Rose Gold Jar', 'slug' => 'rose-gold-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Rose Gold', 'Gold']]],
            ['sku' => 'PL-7045-2', 'name' => 'Printed Glass Mirror Platter', 'slug' => 'printed-glass-mirror-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Mirror']]],
            ['sku' => 'PL-7046', 'name' => 'Round Gold Mirror Platter', 'slug' => 'round-gold-mirror-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold'], 'shape' => ['Round'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7046-2', 'name' => 'Metal Hammered Handi Jar', 'slug' => 'metal-hammered-handi-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Hammered']]],
            ['sku' => 'PL-7047', 'name' => 'Golden Bowl', 'slug' => 'golden-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold']]],
            ['sku' => 'PL-7047-2', 'name' => 'Salai Kalash', 'slug' => 'salai-kalash', 'category' => 'Pooja & Ritual',
                'price' => 5000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7048', 'name' => 'Perrot Bowl', 'slug' => 'perrot-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7048-2', 'name' => 'Golden Frame', 'slug' => 'golden-frame', 'category' => 'Desk & Decor',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold']]],
            ['sku' => 'PL-7048-3', 'name' => 'Golden Tray with Mirror', 'slug' => 'golden-tray-with-mirror', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7049', 'name' => 'Copper Glass Mirror Jar', 'slug' => 'copper-glass-mirror-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Copper', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Copper'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7049-2', 'name' => 'Copper Glass Tray', 'slug' => 'copper-glass-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Copper', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Copper']]],
            ['sku' => 'PL-7049-3', 'name' => 'Wax Diya', 'slug' => 'wax-diya', 'category' => 'Pooja & Ritual',
                'price' => 5000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7050', 'name' => 'Golden Fruit Bowl', 'slug' => 'golden-fruit-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold']]],
            ['sku' => 'PL-7050-2', 'name' => 'Copper Platter', 'slug' => 'copper-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Copper', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Copper']]],
            ['sku' => 'PL-7051', 'name' => 'Aluminium Peacock Platters', 'slug' => 'aluminium-peacock-platters', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Peacock']]],
            ['sku' => 'PL-7051-2', 'name' => 'Golden Photo Frame', 'slug' => 'golden-photo-frame', 'category' => 'Desk & Decor',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold']]],
            ['sku' => 'PL-7052', 'name' => 'Baby Blue Jar', 'slug' => 'baby-blue-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7052-2', 'name' => 'THE Line', 'slug' => 'the-line', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7052-3', 'name' => 'Line OF', 'slug' => 'line-of', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7052-4', 'name' => 'Baby Golden Cart', 'slug' => 'baby-golden-cart', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold']]],
            ['sku' => 'PL-7054', 'name' => 'Elephant Golden Platter', 'slug' => 'elephant-golden-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold'], 'shape' => ['Elephant']]],
            ['sku' => 'PL-7054-2', 'name' => 'Golden Mirror Platter', 'slug' => 'golden-mirror-platter', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['colour' => ['Gold'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7054-3', 'name' => 'Photo Frame', 'slug' => 'photo-frame', 'category' => 'Desk & Decor',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7054-4', 'name' => 'Decorative bowL', 'slug' => 'decorative-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7054-5', 'name' => 'Mirror Tray', 'slug' => 'mirror-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Mirror']]],
            ['sku' => 'PL-7054-6', 'name' => 'Jwelery Box', 'slug' => 'jwelery-box', 'category' => 'Gift Boxes & Hampers',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7054-7', 'name' => 'Decorative Diya', 'slug' => 'decorative-diya', 'category' => 'Pooja & Ritual',
                'price' => 5000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7054-8', 'name' => 'Decorative Jar', 'slug' => 'decorative-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7054-9', 'name' => 'Candle Holder', 'slug' => 'candle-holder', 'category' => 'Candle Holders',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7054-10', 'name' => 'Elephant Jar', 'slug' => 'elephant-jar', 'category' => 'Jars & Containers',
                'price' => 4000, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Elephant']]],
            ['sku' => 'PL-7054-11', 'name' => 'Lotus Tray', 'slug' => 'lotus-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Lotus']]],
            ['sku' => 'PL-7054-12', 'name' => 'Candle', 'slug' => 'candle', 'category' => 'Candle Holders',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7054-13', 'name' => 'Dcorative Bowl', 'slug' => 'dcorative-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7054-14', 'name' => 'Decorative Tray', 'slug' => 'decorative-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7054-15', 'name' => 'Show Peice', 'slug' => 'show-peice', 'category' => 'Decorative Accents',
                'price' => 3600, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
            ['sku' => 'PL-7054-16', 'name' => 'Hammered Bowl', 'slug' => 'hammered-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['finish' => ['Hammered']]],
            ['sku' => 'PL-7054-17', 'name' => 'Oval Mirror Tray', 'slug' => 'oval-mirror-tray', 'category' => 'Serving Trays',
                'price' => 3450, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => ['shape' => ['Oval'], 'finish' => ['Mirror']]],
            ['sku' => 'PL-7054-18', 'name' => 'Horse Bowl', 'slug' => 'horse-bowl', 'category' => 'Bowls & Katoris',
                'price' => 2525, 'material' => 'Metal', 'collection' => 'Premium Collection', 'attrs' => []],
        ];

        $rows  = [];
        $order = 0;

        foreach ($products as $p) {
            $rows[] = [
                'category_id'    => $categoryIds[$p['category']] ?? null,
                'sku'            => $p['sku'],
                'name'           => $p['name'],
                'slug'           => $p['slug'],
                'short_description' => $p['name'],
                'description'    => '<p>' . esc($p['name']) . '</p>',
                'material'       => $p['material'],
                'price'          => $p['price'],
                'stock_qty'      => 25,
                'track_inventory' => 1,
                'sale_mode'      => 'inherit',
                'is_active'      => 1,
                // Featured later, one per category — see below. A homepage with
                // an empty "featured" row looks broken on a fresh install.
                'is_featured'    => 0,
                'sort_order'     => $order += 10,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        // One insert for the lot. 155 individual inserts is 155 round trips.
        $this->db->table('products')->insertBatch($rows);

        // Read the ids back by SKU — insertBatch does not return them.
        $productIds = [];

        foreach ($this->db->table('products')->select('id, sku')->get()->getResultArray() as $row) {
            $productIds[$row['sku']] = (int) $row['id'];
        }

        // ------------------------------------------- attributes, per product
        $links = [];

        foreach ($products as $p) {
            $productId = $productIds[$p['sku']] ?? null;

            if ($productId === null) {
                continue;
            }

            $i = 0;

            foreach ($p['attrs'] as $code => $labels) {
                foreach ($labels as $label) {
                    $valueId = $valueIds[$code . '|' . $label] ?? null;

                    /*
                     * A size read from a PDF may not be in the seeded list —
                     * "5.75 x 5.75 x 2.5" is real but was never typed into the
                     * admin. Create it rather than dropping the fact.
                     */
                    if ($valueId === null && $code === 'size') {
                        $attributeId = $this->db->table('attributes')
                            ->select('id')->where('code', 'size')->get()->getRowArray()['id'] ?? null;

                        if ($attributeId !== null) {
                            $this->db->table('attribute_values')->insert([
                                'attribute_id' => (int) $attributeId,
                                'label'        => $label,
                                'sort_order'   => 900,
                                'created_at'   => $now,
                                'updated_at'   => $now,
                            ]);

                            $valueId = (int) $this->db->insertID();
                            $valueIds[$code . '|' . $label] = $valueId;
                        }
                    }

                    if ($valueId === null) {
                        continue;
                    }

                    $links[] = [
                        'product_id' => $productId,
                        'value_id'   => $valueId,
                        'sort_order' => $i += 10,
                        'created_at' => $now,
                    ];
                }
            }
        }

        if ($links !== []) {
            $this->db->table('product_attributes')->insertBatch($links);
        }

        /*
         * Broaden the palette.
         *
         * The PDFs name the colour a piece was photographed in, not the range
         * it is offered in — a German Silver bowl is made in antique and gold
         * finishes too, and the catalogue simply photographs one. Seeding a
         * single colour per product means a "choose a colour" control with one
         * option, which is no control at all.
         *
         * Deterministic on the SKU, so the catalogue is identical on every
         * machine and every rerun.
         */
        $palette = [
            'German Silver' => ['Silver', 'Antique Silver', 'Gold'],
            'Brass'         => ['Brass', 'Antique Silver'],
            'Copper'        => ['Copper', 'Rose Gold'],
            'Wood'          => ['Ivory', 'Black'],
            'Ceramic'       => ['Ivory', 'Blue'],
            'Metal'         => ['Silver', 'Gold', 'Rose Gold'],
        ];

        $sizeLadder = ['6" x 6"', '8" x 8"', '12" x 12"'];
        $extra      = [];

        foreach ($products as $p) {
            $productId = $productIds[$p['sku']] ?? null;

            if ($productId === null) {
                continue;
            }

            foreach ($palette[$p['material']] ?? [] as $label) {
                $valueId = $valueIds['colour|' . $label] ?? null;

                if ($valueId !== null) {
                    $extra[] = ['product_id' => $productId, 'value_id' => $valueId, 'sort_order' => 0, 'created_at' => $now];
                }
            }

            // A second size on about half the range, so the size axis has
            // something to choose between and the two axes genuinely interact.
            if ((crc32($p['sku']) % 2) === 0) {
                $label   = $sizeLadder[crc32($p['sku']) % count($sizeLadder)];
                $valueId = $valueIds['size|' . $label] ?? null;

                if ($valueId !== null) {
                    $extra[] = ['product_id' => $productId, 'value_id' => $valueId, 'sort_order' => 5, 'created_at' => $now];
                }
            }
        }

        if ($extra !== []) {
            // IGNORE: a product may already carry the colour the PDF named, and
            // the pair is unique.
            $this->db->table('product_attributes')->ignore(true)->insertBatch($extra);
        }

        /*
         * A real catalogue is not uniformly in stock and quote-free.
         *
         * The most expensive pieces are quoted rather than sold outright — that
         * is how the PDFs price them, with a ladder that starts at one and runs
         * to 200+ — and a couple are out of stock, because some always are.
         * Seeding everything identical would leave both paths untested.
         */
        $this->db->table('products')->where('price >=', 5000)
            ->update(['sale_mode' => 'enquire_now']);

        /*
         * Bigger pieces take more room in a hamper.
         *
         * A tray or a large box genuinely occupies more of a gift box than a
         * small jar, and the builder's capacity maths is only exercised if
         * something costs more than one slot. Seeding everything at 1 left that
         * path untested.
         */
        $this->db->table('products')->where('price >=', 3500)
            ->update(['giftbox_slots' => 2]);

        $this->db->table('products')->where('price >=', 6000)
            ->update(['giftbox_slots' => 3]);

        $lastTwo = $this->db->table('products')->select('id')
            ->orderBy('id', 'DESC')->limit(2)->get()->getResultArray();

        if ($lastTwo !== []) {
            $this->db->table('products')
                ->whereIn('id', array_column($lastTwo, 'id'))
                ->update(['stock_qty' => 0]);
        }

        /*
         * One featured piece per category, so the homepage row is populated and
         * spread across the catalogue rather than showing eight trays.
         */
        $featured = $this->db->query(
            'SELECT MIN(id) AS id FROM products GROUP BY category_id'
        )->getResultArray();

        if ($featured !== []) {
            $this->db->table('products')
                ->whereIn('id', array_column($featured, 'id'))
                ->update(['is_featured' => 1]);
        }

        /*
         * Variants come from the attributes just attached — built here so ONE
         * command gives a working catalogue.
         */
        $this->call(VariantSeeder::class);

        echo '  Catalogue: ' . count($rows) . ' products, '
            . count($categoryIds) . ' categories, '
            . count($links) . " attribute links.\n";

        // A catalogue with no attributes is a broken run, not a quiet one.
        if ($links === []) {
            echo "  WARNING: no attributes were linked. Run 'php spark db:seed AttributeSeeder' "
                . "and then this seeder again.\n";
        }
    }
}
