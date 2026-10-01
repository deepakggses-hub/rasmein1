<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Throwable;

/**
 * The Diwali Hamper Collection — 50 hampers, DK01 to DK50, taken from the
 * shop's own "Diwali catalogue 2026 New.pdf".
 *
 * ADDITIVE, NOT DESTRUCTIVE
 *
 * Like CandleStandSeeder and unlike ProductCatalogueSeeder, this truncates
 * nothing and is idempotent on SKU: a rerun updates the copy and the price
 * rather than inserting a second set. It therefore has to run AFTER
 * ProductCatalogueSeeder in DatabaseSeeder, or that seeder's truncate takes
 * these fifty with it.
 *
 * THE PRICES ARE REAL
 *
 * The catalogue prints an MRP on every page, so unlike the candle stands
 * there is no placeholder and no "Price to confirm" eyebrow. The figures
 * below are the catalogue's own.
 *
 * THE SOURCE PDF HAS NO TEXT LAYER
 *
 * Every page is a single flattened CMYK image, so the names, prices and
 * contents were read from the rendered pages rather than extracted. A handful
 * of plain misspellings in the artwork are corrected here — "Fragnance",
 * "Organsor", "Noise Grand 3les" — because a typo repeated across forty
 * product pages reads as carelessness on the storefront. Everything else,
 * including brand spellings such as "Jower Puffs" and "Offikraft", is
 * verbatim. See CLAUDE.md for the full list.
 *
 * IMAGES
 *
 * The photographs were cut from the catalogue pages themselves and placed at
 * uploads/products/2026/09/diwali-gift-hamper-{code}.jpg. A missing file is
 * REPORTED rather than silently linked — a product_images row pointing at
 * nothing renders a broken image and the media picker still offers it.
 */
class DiwaliHamperSeeder extends Seeder
{
    private const CATEGORY = [
        'name'        => 'Diwali Gift Hampers',
        'slug'        => 'diwali-gift-hampers',
        'description' => 'Curated Diwali hampers — sweets, dry fruit, drinkware and light.',
    ];

    /** Where the import placed the photographs, relative to public/. */
    private const IMAGE_DIR = 'uploads/products/2026/09';

    /** Every page carries this line under the title. */
    private const SUBTITLE = 'Festive hamper with curated picks.';

    /**
     * The occasions every hamper is tagged into.
     *
     * `festivals` and `festive-corporate-gifting` are seeded by
     * OccasionSeeder; `diwali` is not, so it is created here if absent.
     *
     * Note the two audiences: `festivals` is retail and
     * `festive-corporate-gifting` is corporate, so one hamper reaches both
     * journeys without either list pretending to be the other.
     */
    private const OCCASION_SLUGS = ['diwali', 'festivals', 'festive-corporate-gifting'];

    /** Created only when it is not already there. */
    private const DIWALI = [
        'name'        => 'Diwali',
        'slug'        => 'diwali',
        'description' => 'Hampers for the festival of lights — sweets, dry fruit, lamps and drinkware.',
    ];

    public function run(): void
    {
        $now      = date('Y-m-d H:i:s');
        $hampers  = $this->hampers();

        $categoryId  = $this->categoryId($now);
        $occasionIds = $this->occasionIds($now);

        $seeded  = 0;
        $updated = 0;
        $missing = [];
        $tagged  = 0;

        foreach ($hampers as $index => $h) {
            $slug  = 'diwali-gift-hamper-' . strtolower($h['code']);
            $short = $this->shortFor($h['contents']);

            $payload = [
                'category_id'         => $categoryId,
                'sku'                 => 'RSM-' . strtoupper($h['code']),
                'name'                => 'Diwali Gift Hamper ' . strtoupper($h['code']),
                'slug'                => $slug,
                'short_description'   => $short,
                'description'         => $this->describe($h),
                'price'               => $h['price'],
                'stock_qty'           => 25,
                'track_inventory'     => 1,
                'eyebrow_label'       => 'Diwali 2026',
                /*
                 * The contents list IS the specification for a hamper: what is
                 * inside is the entire product. One line per item, which is the
                 * shape `composition` is rendered in.
                 */
                'composition'         => implode("\n", $h['contents']),
                'care_note'           => "Store in a cool, dry place away from direct sunlight.\n"
                    . "Consume edible contents by the date printed on each pack.\n"
                    . 'Contents may be substituted with an equivalent item subject to availability.',
                'sale_mode'           => 'inherit',
                // The catalogue offers these retail and in bulk to corporates.
                'audience'            => 'both',
                /*
                 * A hamper is a finished box, not a component: putting one
                 * inside a build-your-own box would be a box in a box.
                 */
                'is_giftbox_eligible' => 0,
                'giftbox_slots'       => 1,
                'is_active'           => 1,
                // The first four carry the homepage row without featuring fifty.
                'is_featured'         => $index < 4 ? 1 : 0,
                'sort_order'          => ($index + 1) * 10,
                'meta_title'          => 'Diwali Gift Hamper ' . strtoupper($h['code']),
                'meta_description'    => mb_substr($short, 0, 255),
                'updated_at'          => $now,
            ];

            $existing = $this->db->table('products')
                ->select('id')->where('sku', $payload['sku'])->get()->getRowArray();

            if ($existing !== null) {
                $this->db->table('products')->where('id', $existing['id'])->update($payload);
                $productId = (int) $existing['id'];
                $updated++;
            } else {
                $payload['created_at'] = $now;
                $this->db->table('products')->insert($payload);
                $productId = (int) $this->db->insertID();
                $seeded++;
            }

            if (! $this->attachImage($productId, $slug, $payload['name'], $short, $now)) {
                $missing[] = $slug . '.jpg';
            }

            /*
             * syncProductOccasions() REPLACES this product's occasion links and
             * leaves its collection links alone, so a rerun is idempotent and
             * nothing else the shop has tagged is disturbed.
             */
            if ($occasionIds !== []) {
                model('CollectionModel')->syncProductOccasions($productId, $occasionIds);
                $tagged++;
            }
        }

        echo '  Diwali Hamper Collection: ' . $seeded . ' added, ' . $updated . " updated.\n";
        echo '  Tagged into ' . count($occasionIds) . ' occasion(s): ' . $tagged . " products.\n";

        if ($occasionIds === []) {
            echo "  WARNING: none of the expected occasions exist, so nothing was tagged.\n";
            echo "           Run OccasionSeeder first.\n";
        }

        if ($missing !== []) {
            echo '  WARNING: ' . count($missing) . ' photograph(s) were not found in public/'
                . self::IMAGE_DIR . ":\n";

            foreach ($missing as $file) {
                echo "    - {$file}\n";
            }
        }
    }

    // =================================================================

    /**
     * Find the Diwali Gift Hampers category, or create it.
     *
     * `path` is written here rather than left to a backfill: a migration
     * running against an empty table cannot fix rows a seeder inserts
     * afterwards, and a category with no path is unreachable at the site root.
     */
    private function categoryId(string $now): ?int
    {
        $row = $this->db->table('categories')
            ->select('id')->where('slug', self::CATEGORY['slug'])->get()->getRowArray();

        if ($row !== null) {
            return (int) $row['id'];
        }

        $sort = (int) ($this->db->table('categories')
            ->selectMax('sort_order', 'm')->get()->getRowArray()['m'] ?? 0);

        $this->db->table('categories')->insert([
            'name'        => self::CATEGORY['name'],
            'slug'        => self::CATEGORY['slug'],
            'path'        => self::CATEGORY['slug'],
            'parent_id'   => null,
            'depth'       => 0,
            'description' => self::CATEGORY['description'],
            'sort_order'  => $sort + 10,
            'is_active'   => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Resolve the three occasions, creating Diwali if it is not there.
     *
     * `festivals` and `festive-corporate-gifting` belong to OccasionSeeder and
     * are NOT created here — a second seeder inventing a row another one owns
     * is how two slightly different copies of the same occasion appear. They
     * are looked up and skipped if genuinely absent.
     *
     * @return list<int>
     */
    private function occasionIds(string $now): array
    {
        $ids = [];

        foreach (self::OCCASION_SLUGS as $slug) {
            $row = $this->db->table('collections')
                ->select('id')->where('slug', $slug)->get()->getRowArray();

            if ($row !== null) {
                $ids[] = (int) $row['id'];

                continue;
            }

            if ($slug !== self::DIWALI['slug']) {
                // Owned by OccasionSeeder. Do not duplicate it.
                continue;
            }

            $sort = (int) ($this->db->table('collections')
                ->selectMax('sort_order', 'm')->get()->getRowArray()['m'] ?? 0);

            $this->db->table('collections')->insert([
                'type' => 'occasion',
                /*
                 * Both journeys. A Diwali hamper goes to a client as readily as
                 * to a cousin, and pinning it to one audience would hide it
                 * from the other for no reason the shop would recognise.
                 */
                'audience'    => 'both',
                'name'        => self::DIWALI['name'],
                'slug'        => self::DIWALI['slug'],
                'description' => self::DIWALI['description'],
                'image'       => '',
                'alt_text'    => '',
                'is_featured' => 1,
                'sort_order'  => $sort + 10,
                'is_active'   => 1,
                /*
                 * No dates, deliberately. An occasion outside its window 404s,
                 * so an ends_at would kill this page the morning after Diwali
                 * while the hampers are still listed everywhere else.
                 */
                'starts_at'        => null,
                'ends_at'          => null,
                'meta_title'       => self::DIWALI['name'] . ' gifts',
                'meta_description' => self::DIWALI['description'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            $ids[] = (int) $this->db->insertID();
        }

        return $ids;
    }

    /**
     * A one-line summary naming what is actually in the box.
     *
     * The catalogue's own subtitle is the same sentence on all fifty pages,
     * so it says nothing that distinguishes one hamper from another. It is
     * kept as the opening clause and the first few contents follow it, which
     * is what a listing card and a search result need.
     */
    private function shortFor(array $contents): string
    {
        // The first line is always the box itself, which is not a highlight.
        $items = array_slice($contents, 1, 3);

        if ($items === []) {
            return self::SUBTITLE;
        }

        $last  = array_pop($items);
        $named = $items === [] ? $last : implode(', ', $items) . ' and ' . $last;

        return self::SUBTITLE . ' Inside: ' . $named . '.';
    }

    /**
     * The description column is HTML and is sanitised on save by ProductModel.
     *
     * This seeder writes through the query builder, which bypasses the model —
     * so the markup is built from a fixed set of tags and every variable part
     * is escaped. Nothing in it comes from a request. Never widen this to
     * accept arbitrary HTML.
     */
    private function describe(array $h): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<p>' . $e(self::SUBTITLE) . '</p>'
            . '<h3>Inside the box</h3><ul>';

        foreach ($h['contents'] as $line) {
            $html .= '<li>' . $e($line) . '</li>';
        }

        return $html . '</ul>'
            . '<p>Available in bulk for corporate gifting, and open to customisation.</p>';
    }

    /**
     * Link the photograph, and register it in the media library.
     *
     * Alt text is written here rather than left blank: product_images.alt_text
     * existed from the start and the product form rendered no field for years,
     * so earlier images shipped silent to a screen reader.
     *
     * @return bool false when the file is not on disk
     */
    private function attachImage(
        int $productId,
        string $slug,
        string $name,
        string $short,
        string $now,
    ): bool {
        $relative = self::IMAGE_DIR . '/' . $slug . '.jpg';

        if (! is_file(FCPATH . $relative)) {
            return false;
        }

        $alt = $name . ' — ' . mb_substr($short, 0, 180);

        $existing = $this->db->table('product_images')
            ->select('id')->where('product_id', $productId)->where('path', $relative)
            ->get()->getRowArray();

        if ($existing === null) {
            $this->db->table('product_images')->insert([
                'product_id' => $productId,
                'path'       => $relative,
                'alt_text'   => $alt,
                'is_primary' => 1,
                'sort_order' => 0,
                'created_at' => $now,
            ]);
        } else {
            $this->db->table('product_images')->where('id', $existing['id'])
                ->update(['alt_text' => $alt, 'is_primary' => 1]);
        }

        /*
         * Into the library too, so the picture is reusable from any screen.
         * remember() returns the existing row when the path matches, so a
         * rerun does not duplicate it. Failure must not fail the seed — the
         * file and the product row are both already correct.
         */
        try {
            model('MediaModel')->remember($relative, $alt);
        } catch (Throwable $e) {
            log_message('warning', 'DiwaliHamperSeeder: media library skipped for {p}: {m}', [
                'p' => $relative,
                'm' => $e->getMessage(),
            ]);
        }

        return true;
    }

    // =================================================================

    /**
     * The fifty hampers, in catalogue order.
     *
     * The first line of `contents` is always the box, which is how the
     * catalogue prints it and what shortFor() relies on.
     */
    private function hampers(): array
    {
        return [
            ['code' => 'DK01', 'price' => 2280.00, 'contents' => [
                'Foldable brown box',
                'Notty Nuts Cashew nuts 100gms',
                'Notty Nuts Pista 100gms',
                'Notty Nuts Almond 100gms',
            ]],
            ['code' => 'DK02', 'price' => 3540.00, 'contents' => [
                'Foldable brown box',
                'Offikraft Eco cork mug 450ml',
                'Notty Nuts protein bomb',
                'Notty Nuts Almond 100gms',
                'Ferrero Rocher T4',
            ]],
            ['code' => 'DK03', 'price' => 2960.00, 'contents' => [
                'Foldable brown box',
                'Notty Nuts Cashew nuts 100gms',
                'Notty Nuts Pista 100gms',
                'Notty Nuts Almond 100gms',
                'Notty Nuts Assorted mixed nuts 100gms',
            ]],
            ['code' => 'DK04', 'price' => 1836.00, 'contents' => [
                'Foldable brown box',
                'Offikraft Desky 2.0',
                'Unwrap happiness oh crumbs osmania cookies 75gms',
                'Iris set of 5 diyas (Assorted Fragrance)',
                'Offikraft Micro steel mug',
            ]],
            ['code' => 'DK05', 'price' => 4640.00, 'contents' => [
                'Foldable brown box',
                'Offikraft Desky 2.0',
                'Notty Nuts Pista 100gms',
                'Offikraft Atom digital clock',
                'Unwrap happiness Dragees 150gms',
            ]],
            ['code' => 'DK06', 'price' => 2472.00, 'contents' => [
                'Foldable brown box',
                'Insulated bottle 500ml',
                'Diya 2pc',
                'Notty Nuts Jower Puffs',
            ]],
            ['code' => 'DK07', 'price' => 2308.00, 'contents' => [
                'Foldable brown box',
                'Offikraft Eco cork mug 450ml',
                'Offikraft Kairo bottle',
                'Unwrap happiness Brittle 125gms',
            ]],
            ['code' => 'DK08', 'price' => 5096.00, 'contents' => [
                'Foldable brown box',
                'Iris Set of 2 Aromatic floating candles',
                'Notty Nuts protein bomb',
                'Offikraft Atom digital clock',
                'Offikraft Mushroom torch',
            ]],
            ['code' => 'DK09', 'price' => 3516.00, 'contents' => [
                'Foldable brown box',
                'Iris Set of 2 Aromatic floating candles',
                'Offikraft Eco cork mug 450ml',
                'Notty Nuts protein bomb',
                'Notty Nuts Pista 100gms',
            ]],
            ['code' => 'DK10', 'price' => 4076.00, 'contents' => [
                'Foldable brown box',
                'Notty Nuts Pista 100gms',
                'Notty Nuts Almond 100gms',
                'Tintbox Brew cup',
                'Unwrap happiness Drippin coffee rolls 120gms',
            ]],
            ['code' => 'DK11', 'price' => 1552.00, 'contents' => [
                'Foldable brown box',
                'Unwrap happiness classico choco rolls 85gms',
                'Iris Set of 2 Aromatic floating candles',
                'Iris Potpourri rose',
            ]],
            ['code' => 'DK12', 'price' => 2380.00, 'contents' => [
                'Foldable brown box',
                'Offikraft Eco cork mug 450ml',
                'Iris set of 5 diyas (Assorted Fragrance)',
                'Unwrap happiness Baked lavash 100gms',
                'Unwrap happiness oh crumbs cookies 75gms',
            ]],
            ['code' => 'DK13', 'price' => 4520.00, 'contents' => [
                'Foldable brown box',
                'Unwrap happiness chocosins 9pcs',
                'Offikraft Kairo bottle',
                'Notty Nuts Assorted mixed nuts 100gms',
                'Solara glass tumbler with sleeve 500ml',
            ]],
            ['code' => 'DK14', 'price' => 5096.00, 'contents' => [
                'Foldable brown box',
                'Notty Nuts protein bomb',
                'Iris Set of 2 Aromatic floating candles',
                'Offikraft Atom digital clock',
                'Offikraft Mushroom torch',
            ]],
            ['code' => 'DK15', 'price' => 4312.00, 'contents' => [
                'Foldable black box',
                'Tamta Copper Tavya A002',
                'Offikraft Eco cork mug 450ml',
                'Unwrap happiness brittle 125gms',
            ]],
            ['code' => 'DK16', 'price' => 4012.00, 'contents' => [
                'Foldable black box',
                'Tamta Copper Tavya A002',
                'Unwrap happiness brittle 125gms',
                'Iris Potpourri rose',
            ]],
            ['code' => 'DK17', 'price' => 4416.00, 'contents' => [
                'Foldable black box',
                'Tamta Copper bottle 900ml',
                'Notty Nuts protein bomb',
                'Unwrap happiness oh crumbs almond cookies 75gms',
            ]],
            ['code' => 'DK18', 'price' => 6004.00, 'contents' => [
                'Foldable black box',
                'Tamta Copper bottle 900ml',
                'Unwrap happiness Baked lavash 100gms',
                'Mivi play',
                'Diya 1PC',
            ]],
            ['code' => 'DK19', 'price' => 2844.00, 'contents' => [
                'Foldable black box',
                'Iris Set of 2 Aromatic floating candles',
                'Notty Nuts Roasted makhana',
                'Offikraft flip bottle',
            ]],
            ['code' => 'DK20', 'price' => 3180.00, 'contents' => [
                'Foldable black box',
                'Notty Nuts Cashew nuts 100gms',
                'Notty Nuts Almond 100gms',
                'Offikraft Mushroom torch',
                'Offikraft Dexter mug',
            ]],
            ['code' => 'DK21', 'price' => 2940.00, 'contents' => [
                'Foldable black box',
                'Unwrap happiness drippin coffee rolls 120gms',
                'Iris set of 5 diyas (Assorted Fragrance)',
                'Offikraft Dexter mug',
                'Offikraft Mushroom torch',
            ]],
            ['code' => 'DK22', 'price' => 4484.00, 'contents' => [
                'Foldable black box',
                'Iris set of 5 diyas (Assorted Fragrance)',
                'Notty Nuts protein bomb',
                'Tamta Copper bottle 900ml',
            ]],
            ['code' => 'DK23', 'price' => 2292.00, 'contents' => [
                'Foldable black box',
                'Offikraft Steel sip bottle',
                'Unwrap happiness classico choco rolls 85gms',
                'Offikraft Micro steel mug',
                'Iris Set of 2 Aromatic floating candles',
            ]],
            ['code' => 'DK24', 'price' => 3780.00, 'contents' => [
                'Foldable black box',
                'Notty Nuts Cashew nuts 100gms',
                'Notty Nuts Almond 100gms',
                'Notty Nuts Pista 100gms',
                'Notty Nuts protein bomb',
                'Notty Nuts Green raisins 100gms',
            ]],
            ['code' => 'DK25', 'price' => 2240.00, 'contents' => [
                'Foldable black box',
                'Offikraft Clark bottle',
                'Offikraft Micro steel mug',
                'Diffuser',
            ]],
            ['code' => 'DK26', 'price' => 11344.00, 'contents' => [
                'Foldable black box',
                'Tamta Copper Tavya A002',
                'Unwrap happiness Dragees 150gms',
                'Mivi roam 2',
                'Noise Grand 3',
            ]],
            ['code' => 'DK27', 'price' => 7580.00, 'contents' => [
                'Foldable black box',
                'Offikraft Bistro bottle',
                'Noise Grand 3',
                'Notty Nuts protein bomb',
            ]],
            ['code' => 'DK28', 'price' => 4696.00, 'contents' => [
                'Foldable black box',
                'Unwrap happiness Dragees 150gms',
                'Notty Nuts Green raisins 100gms',
                'Notty Nuts Cashew nuts 100gms',
                'Tintbox sipper bottle',
            ]],
            ['code' => 'DK29', 'price' => 2904.00, 'contents' => [
                'Foldable black box',
                'Unwrap happiness oh crumbs cookies 75gms',
                'Solara glass tumbler with sleeve 500ml',
                'Offikraft Clark bottle',
            ]],
            ['code' => 'DK30', 'price' => 4040.00, 'contents' => [
                'Diwali spl box',
                'Notty Nuts Almond 100gms',
                'Notty Nuts Pista 100gms',
                'Offikraft Atom digital clock',
                'Diya 1PC',
            ]],
            ['code' => 'DK31', 'price' => 2700.00, 'contents' => [
                'Diwali spl box',
                'Offikraft Micro steel mug',
                'Proust Fragrance',
                'Unwrap happiness oh crumbs cookies 75gms',
                'Diya 1PC',
            ]],
            ['code' => 'DK32', 'price' => 1840.00, 'contents' => [
                'Diwali spl box',
                'Offikraft Dexter mug',
                'Notty Nuts Almond 100gms',
                'Diya 1PC',
            ]],
            ['code' => 'DK33', 'price' => 1552.00, 'contents' => [
                'Diwali spl box',
                'Unwrap happiness classico choco rolls 85gms',
                'Iris Set of 2 Aromatic floating candles',
                'Notty Nuts Green raisins 100gms',
            ]],
            ['code' => 'DK34', 'price' => 2220.00, 'contents' => [
                'Diwali spl box',
                'Notty Nuts Green raisins 100gms',
                'Notty Nuts Cashew nuts 100gms',
                'Notty Nuts Almond 100gms',
                'Offikraft Micro steel mug',
            ]],
            ['code' => 'DK35', 'price' => 4280.00, 'contents' => [
                'Diwali spl box',
                'Proust Fragrance',
                'Offikraft Kairo bottle',
                'Unwrap happiness Dragees 150gms',
                'Notty Nuts Almond 100gms',
            ]],
            ['code' => 'DK36', 'price' => 4688.00, 'contents' => [
                'Diwali spl box',
                'Iris Set of 2 Aromatic floating candles',
                'Solara glass tumbler with sleeve 500ml',
                'Iris Potpourri rose',
                'Insulated bottle 500ml',
                'Notty Nuts Roasted pumpkin seeds 200gms',
            ]],
            ['code' => 'DK37', 'price' => 4980.00, 'contents' => [
                'Diwali spl box',
                'Notty Nuts Green raisins 100gms',
                'Notty Nuts Cashew nuts 100gms',
                'Unwrap happiness brittle 125gms',
                'Unwrap happiness oh crumbs cookies 75gms',
                'Iris set of 5 diyas (Assorted Fragrance)',
                'HPSK 2134 Skross Gadget Organiser',
            ]],
            ['code' => 'DK38', 'price' => 4080.00, 'contents' => [
                'Diwali spl box',
                'HPSK 2134 Skross Gadget Organiser',
                'Notty Nuts Roasted Makhana',
                '4 in 1 diya set',
            ]],
            ['code' => 'DK39', 'price' => 5500.00, 'contents' => [
                'Foldable black box',
                'Notty Nuts Green raisins 100gms',
                'Notty Nuts Cashew nuts 100gms',
                'Iris set of 5 diyas (Assorted Fragrance)',
                'Offikraft Insulated bottle 500ml',
                'HPSK 2134 Skross Gadget Organiser',
            ]],
            ['code' => 'DK40', 'price' => 6984.00, 'contents' => [
                'Diwali spl box',
                'Notty Nuts Assorted mixed nuts 100gms',
                'Notty Nuts Pista 100gms',
                'Iris Set of 2 Aromatic floating candles',
                'Offikraft Mushroom torch',
                'Ferrero Rocher T4',
                'Tommy greenbelt waist pouch',
            ]],
            ['code' => 'DK41', 'price' => 6420.00, 'contents' => [
                'Diwali spl box',
                'Tommy grayling Toiletry case',
                'Offikraft Atom digital clock',
                'Iris Set of 2 Aromatic floating candles',
                'Unwrap happiness brittle 125gms',
            ]],
            ['code' => 'DK42', 'price' => 7092.00, 'contents' => [
                'Diwali spl box',
                'Notty Nuts Green raisins 100gms',
                'Notty Nuts Cashew nuts 100gms',
                'Notty Nuts Almond 100gms',
                'Notty Nuts Pista 100gms',
                'Iris set of 5 diyas (Assorted Fragrance)',
                'Ferrero Rocher T4',
                'Tommy Sunbury Reporter',
            ]],
            ['code' => 'DK43', 'price' => 6996.00, 'contents' => [
                'Diwali spl box',
                'Iris Set of 2 Aromatic floating candles',
                'Unwrap happiness chocosins 9pcs',
                'Offikraft Mushroom torch',
                'Tommy Sunbury Reporter',
            ]],
            ['code' => 'DK44', 'price' => 4360.00, 'contents' => [
                'Diwali spl box',
                'Offikraft Eco cork mug 450ml',
                'Unwrap happiness oh crumbs cookies 75gms',
                'Offikraft Kairo bottle',
                'Notty Nuts protein bomb',
                'Ferrero Rocher T4',
                'Iris set of 5 diyas (Assorted Fragrance)',
            ]],
            ['code' => 'DK45', 'price' => 4020.00, 'contents' => [
                'Diwali spl box',
                'Notty Nuts protein bomb',
                'Offikraft Kairo bottle',
                'Ferrero Rocher T4',
                'Offikraft Eco cork mug 450ml',
                'Unwrap happiness Baked lavash',
            ]],
            ['code' => 'DK46', 'price' => 4400.00, 'contents' => [
                'Diwali spl box',
                'Notty Nuts Green raisins 100gms',
                'Notty Nuts Cashew nuts 100gms',
                'Notty Nuts Almond 100gms',
                'Notty Nuts Pista 100gms',
                'Unwrap happiness oh crumbs cookies 75gms',
                'Offikraft Kairo bottle',
                'Offikraft Eco cork mug 450ml',
            ]],
            ['code' => 'DK47', 'price' => 3616.00, 'contents' => [
                'Diwali spl box',
                'Offikraft Insulated bottle 500ml',
                'Iris Set of 2 Aromatic floating candles',
                'Notty Nuts Jower puffs',
                'Notty Nuts Roasted Makhana',
            ]],
            ['code' => 'DK48', 'price' => 6348.00, 'contents' => [
                'Black foldable box',
                'Solara glass tumbler with sleeve 500ml',
                'Notty Nuts Green raisins 100gms',
                'Notty Nuts Cashew nuts 100gms',
                'Noise Grand 3',
            ]],
            ['code' => 'DK49', 'price' => 3112.00, 'contents' => [
                'Black foldable box',
                'Solara motivational bottles 1ltr',
                'Offikraft Micro steel mug',
                'Diffuser',
            ]],
            ['code' => 'DK50', 'price' => 5140.00, 'contents' => [
                'Diwali spl box',
                'Unwrap happiness oh crumbs cookies 75gms',
                'Iris Set of 2 Aromatic floating candles',
                'Noise Grand 3',
            ]],
        ];
    }
}
