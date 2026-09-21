<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Throwable;

/**
 * The Candle Stand Collection — 14 pieces from the shop's own description
 * workbook.
 *
 * ADDITIVE, NOT DESTRUCTIVE
 *
 * Unlike ProductCatalogueSeeder, this one truncates nothing. It is one
 * collection arriving alongside a catalogue that already exists, and it is
 * idempotent on SKU: a rerun updates the copy rather than inserting a second
 * set. That matters because the descriptions are the part most likely to be
 * revised, and re-running is how a revision reaches the site.
 *
 * PRICES ARE NOT IN THE WORKBOOK
 *
 * The spreadsheet carries names, image filenames and prose — no prices, no
 * sizes, no stock. Every piece therefore seeds at PLACEHOLDER_PRICE and is
 * flagged in the admin listing by an eyebrow label of "Price to confirm", so
 * nobody ships one by accident. Set the real figures in Admin → Products, or
 * edit the constant below and re-run.
 *
 * IMAGES
 *
 * The photographs are expected at uploads/products/2026/09/{slug}.jpg with
 * their responsive ladder beside them, which is where the import put them. A
 * missing file is reported rather than silently linked — a product_images row
 * pointing at nothing renders a broken image on the storefront and the media
 * picker still offers it.
 */
class CandleStandSeeder extends Seeder
{
    /**
     * What every piece costs until someone says otherwise.
     *
     * A single obvious figure, not a plausible-looking spread: a made-up price
     * that looks researched is far more dangerous than one that is visibly a
     * placeholder.
     */
    private const PLACEHOLDER_PRICE = 2999.00;

    /** Set on every piece, so an unpriced product is obvious in the listing. */
    private const PRICE_EYEBROW = 'Price to confirm';

    private const CATEGORY = [
        'name'        => 'Candle Holders',
        'slug'        => 'candle-holders',
        'description' => 'Votives and stands, for light that flatters a room.',
    ];

    /** Where the import placed the photographs, relative to public/. */
    private const IMAGE_DIR = 'uploads/products/2026/09';

    public function run(): void
    {
        $now      = date('Y-m-d H:i:s');
        $products = $this->products();

        $categoryId = $this->categoryId($now);

        $seeded  = 0;
        $updated = 0;
        $missing = [];

        foreach ($products as $index => $p) {
            $payload = [
                'category_id'      => $categoryId,
                'sku'              => $p['sku'],
                'name'             => $p['name'],
                'slug'             => $p['slug'],
                'short_description' => $p['short'],
                'description'      => $this->describe($p),
                'price'            => self::PLACEHOLDER_PRICE,
                'stock_qty'        => 25,
                'track_inventory'  => 1,
                'material'         => $p['material'],
                'eyebrow_label'    => self::PRICE_EYEBROW,
                'composition'      => implode("\n", $p['specs']),
                'care_note'        => implode("\n", $p['care']),
                'sale_mode'        => 'inherit',
                'audience'         => 'both',
                'is_giftbox_eligible' => 1,
                'giftbox_slots'    => 2,
                'is_active'        => 1,
                // The first three carry the row on the homepage, so a fresh
                // install has something there without featuring all fourteen.
                'is_featured'      => $index < 3 ? 1 : 0,
                'sort_order'       => ($index + 1) * 10,
                'meta_title'       => $p['name'],
                'meta_description' => mb_substr($p['short'], 0, 255),
                'updated_at'       => $now,
            ];

            $existing = $this->db->table('products')
                ->select('id')->where('sku', $p['sku'])->get()->getRowArray();

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

            if (! $this->attachImage($productId, $p, $now)) {
                $missing[] = $p['image'];
            }
        }

        echo '  Candle Stand Collection: ' . $seeded . ' added, ' . $updated . " updated.\n";

        if ($missing !== []) {
            echo '  WARNING: ' . count($missing) . " photograph(s) were not found in public/"
                . self::IMAGE_DIR . ":\n";

            foreach ($missing as $file) {
                echo "    - {$file}\n";
            }
        }

        echo '  NOTE: the workbook carries no prices. All ' . count($products)
            . ' pieces are at ' . number_format(self::PLACEHOLDER_PRICE, 2)
            . " and labelled \"" . self::PRICE_EYEBROW . "\".\n";
    }

    // =================================================================

    /**
     * Find the Candle Holders category, or create it.
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
            // Top level, so the materialised path is just the slug.
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
     * The description column is HTML and is sanitised on save by ProductModel.
     *
     * This seeder writes through the query builder, which bypasses the model —
     * so the markup here is built from a fixed set of tags and the only
     * variable parts are escaped. Nothing in it comes from a request.
     */
    private function describe(array $p): string
    {
        $e    = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<p>' . $e($p['overview']) . '</p>';

        if ($p['features'] !== []) {
            $html .= '<h3>Key features</h3><ul>';

            foreach ($p['features'] as $line) {
                // "Label: sentence" — the label carries the scanning weight, so
                // it is bolded. A line without a colon is emitted whole rather
                // than split on a guess.
                $parts = explode(':', $line, 2);

                $html .= count($parts) === 2 && trim($parts[1]) !== ''
                    ? '<li><strong>' . $e(trim($parts[0])) . ':</strong> ' . $e(trim($parts[1])) . '</li>'
                    : '<li>' . $e($line) . '</li>';
            }

            $html .= '</ul>';
        }

        return $html;
    }

    /**
     * Link the photograph, and register it in the media library.
     *
     * Alt text is written here rather than left blank: product_images.alt_text
     * existed from the start and the product form never rendered a field, so
     * every earlier image shipped silent to a screen reader.
     *
     * @return bool false when the file is not on disk
     */
    private function attachImage(int $productId, array $p, string $now): bool
    {
        $relative = self::IMAGE_DIR . '/' . $p['image'];

        if (! is_file(FCPATH . $relative)) {
            return false;
        }

        $alt = $p['name'] . ' — ' . ($p['material'] !== '' ? $p['material'] : 'gold finish') . ' candle stand';

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
            model(\App\Models\MediaModel::class)->remember($relative, $p['image'], 'products');
        } catch (Throwable $e) {
            log_message('error', 'Media library write failed for {p}: {m}', ['p' => $relative, 'm' => $e->getMessage()]);
        }

        return true;
    }

    /**
     * Generated from "Candle Stand Description.xlsx" — the shop's own copy,
     * not rewritten. Re-generate rather than edit by hand if the workbook
     * changes.
     *
     * @return list<array<string, mixed>>
     */
    private function products(): array
    {
        return [
            [
                'sku'      => 'RSM-CS-001',
                'name'     => 'Aurelia Crystal Branch Candle Stand',
                'slug'     => 'aurelia-crystal-branch-candle-stand',
                'image'    => 'aurelia-crystal-branch-candle-stand.jpg',
                'material' => 'High-Shine Metallic Gold',
                'short'    => 'Elevate your home décor with the Aurelia Crystal Branch Candle Stand, a show-stopping centerpiece designed to catch light from every angle.',
                'overview' => 'Elevate your home décor with the Aurelia Crystal Branch Candle Stand, a show-stopping centerpiece designed to catch light from every angle. Featuring delicate gold-finished metal branches accented with multifaceted crystal drops, this piece frames an amber-tinted glass hurricane cylinder to create a warm, inviting glow.',
                'features' => [
                    'Botanical-Inspired Design: Intricate gold metallic stems curving into crystal leaf-like drops evoke a luminous botanical nest.',
                    'Amber Glass Hurricane: A decorative amber glass cylinder nestles at the core, softening and enhancing candlelight for an atmospheric ambiance.',
                    'Sturdy Pedestal Base: Built with a weighted, polished gold base for complete stability on dining tables, mantels, or sideboards.',
                    'Versatile Styling: Designed for votive candles, tea lights, or LED pillar candles.',
                ],
                'specs'    => [
                    'Finish: High-Shine Metallic Gold',
                    'Placement: Perfect for dining room centerpieces, festive table layouts, mantel décor, or entryway consoles',
                ],
                'care'     => [
                    'Wipe clean with a soft, dry microfiber cloth.',
                    'Avoid using harsh chemical polishers to protect the gold finish.',
                ],
            ],
            [
                'sku'      => 'RSM-CS-002',
                'name'     => 'Crystal Blossom Candle Stand',
                'slug'     => 'crystal-blossom-candle-stand',
                'image'    => 'crystal-blossom-candle-stand.jpg',
                'material' => 'Polished Gold',
                'short'    => 'Bring understated luxury and warm brilliance into your space with the Crystal Bloosom Candle Stand.',
                'overview' => 'Bring understated luxury and warm brilliance into your space with the Crystal Bloosom Candle Stand. Crafted with fluid gold-finished metal tendrils adorned with faceted crystal teardrops, this piece cradles a warm amber glass votive cylinder. When lit, candlelight refracts through the surrounding crystals, casting a radiant glow across dining tables, mantels, and entryway consoles.',
                'features' => [
                    'Crystal-Beaded Botanical Design: Elegant metal branches curve outward, encrusted with faceted crystal drops that catch and reflect natural light and candlelight.',
                    'Amber Glass Hurricane Core: An inner amber glass sleeve with a decorative lace-embossed metal trim softens the flame\'s glow for an atmospheric setting.',
                    'Sculpted Metallic Pedestal: Designed with a weighted, polished gold pedestal base that ensures stability while adding classic height and presence.',
                    'Versatile Accent: Perfect for holding votive candles, tea lights, or flameless LED pillars.',
                ],
                'specs'    => [
                    'Finish: Polished Gold',
                    'Style: Luxury / Glam / Contemporary Vintage',
                    'Package Includes: 1 x Gold Crystal Candle Stand, 1 x Amber Glass Votive Cup',
                ],
                'care'     => [
                    'Dust regularly with a soft, clean microfiber cloth.',
                    'Wipe the glass cup gently with glass cleaner when cool.',
                    'Avoid abrasive sponges or harsh chemicals to protect the gold finish.',
                ],
            ],
            [
                'sku'      => 'RSM-CS-003',
                'name'     => 'Golden Meadow Candle Stand',
                'slug'     => 'golden-meadow-candle-stand',
                'image'    => 'golden-meadow-candle-stand.jpg',
                'material' => 'Glossy Metallic Gold',
                'short'    => 'Add an enchanting touch of warmth and glamour to your space with the Golden Meadow Candle Stand.',
                'overview' => 'Add an enchanting touch of warmth and glamour to your space with the Golden Meadow Candle Stand. Designed with delicate, twisted gold-tone branches extending upward like blooming stems, each wire is tipped with sparkling faceted teardrop crystals. Nestled at the heart is an amber-tinted glass cylinder that casts a soft, radiant glow when lit, creating a cozy and luxurious atmosphere.',
                'features' => [
                    'Faceted Crystal Detailing: Features brilliant, glass teardrop crystal "buds" set on textured gold stems that brilliantly catch and refract ambient light.',
                    'Warm Amber Glass Cup: An inner amber glass hurricane sleeve softens flickering candlelight for a warm, inviting glow.',
                    'Polished Gold Base: Complete with a weighted, polished metallic gold pedestal base that offers elegance and reliable stability.',
                    'Versatile Decor Piece: Sized ideal for tealights, votive candles, or small flameless LED candles.',
                ],
                'specs'    => [
                    'Finish: Glossy Metallic Gold',
                    'Style: Luxury / Glam / Contemporary Vintage',
                    'Package Includes: 1 x Crystal Branch Base with Amber Glass Insert',
                ],
                'care'     => [
                    'Dust gently with a dry, soft microfiber cloth.',
                    'Clean the amber glass cylinder with a damp cloth or glass cleaner when cool.',
                    'Avoid harsh chemical cleaners or abrasive materials on metal components to protect the finish.',
                ],
            ],
            [
                'sku'      => 'RSM-CS-004',
                'name'     => 'Luxe Prism Candle Stand',
                'slug'     => 'luxe-prism-candle-stand',
                'image'    => 'luxe-prism-candle-stand.jpg',
                'material' => 'Polished Metallic Gold',
                'short'    => 'Add an atmosphere of regal warmth to your interiors with the Luxe Prism Candle Stand.',
                'overview' => 'Add an atmosphere of regal warmth to your interiors with the Luxe Prism Candle Stand. Handcrafted with curving, textured gold-tone branches crowned with multifaceted crystal teardrops, this piece gracefully surrounds a central amber glass hurricane cylinder. When illuminated, the inner glow casts mesmerizing reflections across the surrounding crystals, transforming any surface into an opulent focal point.',
                'features' => [
                    'Faceted Teardrop Crystals: Sparkling crystal "petals" sit atop slender gilded branches, capturing natural sunlight by day and scattering warm candlelight by night.',
                    'Amber Glass Hurricane Sleeve: A smooth amber glass core creates a rich, soothing glow while shielding candle flames.',
                    'Contoured Pedestal Base: Raised on a sculpted, polished gold metallic pillar base engineered for exceptional balance and timeless elegance.',
                    'Multi-Occasion Styling: Holds standard tealights, votive candles, or small LED pillar candles.',
                ],
                'specs'    => [
                    'Finish: Polished Metallic Gold',
                    'Style: Glam / Contemporary Luxury / Botanical',
                    'Package Includes: 1 x Gold Crystal Branch Stand, 1 x Amber Glass Cylinder Insert',
                ],
                'care'     => [
                    'Wipe metal and crystal surfaces gently with a soft, dry microfiber cloth.',
                    'Clean the glass sleeve with a mild glass cleaner once cool.',
                    'Keep away from abrasive pads and strong chemical cleaners to preserve the polished gold lustre.',
                ],
            ],
            [
                'sku'      => 'RSM-CS-005',
                'name'     => 'Imperial Ember Candle Stand',
                'slug'     => 'imperial-ember-candle-stand',
                'image'    => 'imperial-ember-candle-stand.jpg',
                'material' => 'High-Shine Metallic Gold',
                'short'    => 'Elevate your home accent collection with Imperial Ember Candle Stand.',
                'overview' => 'Elevate your home accent collection with Imperial Ember Candle Stand. Elevated on a tall, sculpted pedestal base, this statement piece features flowing, textured gold branches adorned with sparkling faceted crystal teardrops. At its center sits an amber-tinted glass hurricane cylinder that warmly diffuses candlelight, casting a radiant, golden glow across dining settings, console tables, and mantels.',
                'features' => [
                    'Statuesque Pedestal Height: Raised on an elongated, double-tiered polished gold stem that creates dramatic visual height on any table layout.',
                    'Faceted Crystal Leaves: Delicate gold branches flare outward, crowned with crystal drops that reflect light from all angles.',
                    'Amber Glass Hurricane Sleeve: The warm amber glass core softens live flame flicker, creating an inviting, opulent ambiance.',
                    'Balanced & Sturdy: A weighted, mirror-finish gold round base provides complete stability for safe tabletop display.',
                ],
                'specs'    => [
                    'Finish: High-Shine Metallic Gold',
                    'Style: Luxury / Glam / Contemporary Vintage',
                    'Package Includes: 1 x Elevated Gold Crystal Candle Stand, 1 x Amber Glass Insert',
                ],
                'care'     => [
                    'Wipe metal and crystal details with a soft, dry microfiber cloth.',
                    'Clean the glass insert gently with glass cleaner when cool.',
                    'Avoid harsh chemical cleaners or abrasive pads to protect the metallic gold finish.',
                ],
            ],
            [
                'sku'      => 'RSM-CS-006',
                'name'     => 'Diamond Crest Crystal Candle Stand',
                'slug'     => 'diamond-crest-crystal-candle-stand',
                'image'    => 'diamond-crest-crystal-candle-stand.jpg',
                'material' => 'Polished Mirror Gold & Clear Crystal',
                'short'    => 'Make a dramatic statement with Diamond Crest Crystal Candle Stand, an exquisite fusion of high-shine gold artistry and precision-cut crystal.',
                'overview' => 'Make a dramatic statement with Diamond Crest Crystal Candle Stand, an exquisite fusion of high-shine gold artistry and precision-cut crystal. Designed like a gilded tree of light, symmetrical branches extend outward, each tipped with faceted crystal teardrops. The centerpiece is crowned with a brilliant diamond-cut crystal top, resting atop a thick crystal-and-gold pedestal base for an breathtaking display of opulence.',
                'features' => [
                    'Diamond-Cut Crown: Features a large, multifaceted diamond crystal top designed to securely anchor taper or votive candles while catching light from every direction.',
                    'Tiered Crystal Branching: Symmetrical metallic gold stems branch outward with faceted crystal droplets that refract light vertically along the central column.',
                    'Dual-Layered Crystal Base: Supported by a solid, heavy-set clear crystal circular base paired with a polished gold pedestal to ensure maximum stability and reflection.',
                    'Tall Architectural Silhouette: Designed to bring elegant verticality and sophisticated drama to dining centerpieces, mantelpieces, and grand foyers.',
                ],
                'specs'    => [
                    'Finish: Polished Mirror Gold & Clear Crystal',
                    'Style: Modern Luxury / Glam / Art Deco Elegance',
                    'Package Includes: 1 x Diamond Crest Tall Crystal Candle Stand',
                ],
                'care'     => [
                    'Dust carefully with a lint-free, soft microfiber cloth to maintain optical clarity.',
                    'Use a mild glass cleaner directly on the crystal surfaces when necessary, avoiding abrasive chemicals on the gold metal finish.',
                    'Handle gently near the crystal branches during placement.',
                ],
            ],
            [
                'sku'      => 'RSM-CS-007',
                'name'     => 'Golden Rose Crown Candle Stand',
                'slug'     => 'golden-rose-crown-candle-stand',
                'image'    => 'golden-rose-crown-candle-stand.jpg',
                'material' => 'Polished Metallic Gold',
                'short'    => 'Add timeless romance and warmth to your home with Golden Rose Crown Candle Stand.',
                'overview' => 'Add timeless romance and warmth to your home with Golden Rose Crown Candle Stand. Featuring a ring of sculpted, metallic gold roses wrapping around the base of a clear amber glass hurricane cylinder, this piece elevates candlelight into an artistic statement. Set atop a slender stem and weighted, high-shine pedestal base, it brings an aura of sophisticated charm to dining setups, consoles, and festive table layouts.',
                'features' => [
                    'Sculpted Floral Crown: Detailed, metallic gold rose blooms encircle the base of the glass, creating a rich botanical accent.',
                    'Amber Glass Hurricane Core: A tall, amber-tinted glass cylinder diffuses live flame flicker into a soft, golden glow while shielding the candle.',
                    'Polished Pedestal Base: Raised on a slender metallic pillar and heavy-set, mirror-finish round base for complete balance and elegance.',
                    'Versatile Lighting Accent: Accommodates tea lights, votive candles, or small pillar candles.',
                ],
                'specs'    => [
                    'Finish: Polished Metallic Gold',
                    'Style: Romantic Luxury / Modern Vintage / Botanical Glam',
                    'Package Includes: 1 x Rose Crown Gold Stand, 1 x Amber Glass Hurricane Insert',
                ],
                'care'     => [
                    'Dust metal parts gently with a soft, dry microfiber cloth.',
                    'Clean the amber glass cylinder with a damp cloth or glass cleaner once cool.',
                    'Avoid abrasive sponges or chemical polishes to protect the gold finish.',
                ],
            ],
            [
                'sku'      => 'RSM-CS-008',
                'name'     => 'Waterfall Crystal Candle Stand',
                'slug'     => 'waterfall-crystal-candle-stand',
                'image'    => 'waterfall-crystal-candle-stand.jpg',
                'material' => 'Polished Metallic Gold with Clear Crystals',
                'short'    => 'Bring showstopping glamour and architectural beauty to your space with Waterfall Crystal Candle Stand.',
                'overview' => 'Bring showstopping glamour and architectural beauty to your space with Waterfall Crystal Candle Stand. Highlighted by a cascading skirt of clear, arced crystal prisms, this piece catches and diffuses light in every direction. Fine rhinestone embellishments encircle both the cup holder and the base, supporting a warm amber glass hurricane cylinder that turns candlelight into a dazzling focal point.',
                'features' => [
                    'Cascading Crystal Fringe: A skirt of curved glass crystal tendrils creates a dramatic, fountain-like silhouette beneath the flame.',
                    'Sparkling Rhinestone Bands: Detailed with high-density rhinestone pavé bands around the base rim and glass holder for maximum sparkle.',
                    'Amber Glass Hurricane Core: Holds tealights, votive candles, or small pillars inside a tall amber-tinted glass sleeve that softens flickering light.',
                    'Sculpted Gold Pedestal: Crafted with a flared, high-shine gold pedestal stem for robust stability and elegant height.',
                ],
                'specs'    => [
                    'Finish: Polished Metallic Gold with Clear Crystals',
                    'Style: High Glam / Luxury Statement / Art Deco',
                    'Package Includes: 1 x Gold Crystal Fringe Pedestal Stand, 1 x Amber Glass Insert',
                ],
                'care'     => [
                    'Gently dust crystal fringe and metal surfaces using a soft, lint-free microfiber cloth.',
                    'Wipe the glass hurricane cylinder with glass cleaner once completely cool.',
                    'Keep away from harsh chemicals or abrasive materials to maintain the polished gold finish and rhinestone brilliance.',
                ],
            ],
            [
                'sku'      => 'RSM-CS-009',
                'name'     => 'Regal Crystal Candle Stand',
                'slug'     => 'regal-crystal-candle-stand',
                'image'    => 'regal-crystal-candle-stand.jpg',
                'material' => 'Polished Metallic Gold with Clear Crystals',
                'short'    => 'Bring understated luxury and glittering elegance to your home with Regal Crystal Candle Stand.',
                'overview' => 'Bring understated luxury and glittering elegance to your home with Regal Crystal Candle Stand. Featuring a central faceted clear crystal sphere resting between sparkling rhinestone pavé bands, this piece is topped with a clear amber glass hurricane cylinder. Designed to reflect ambient light and warm flame flicker, it transforms dining tables, consoles, and mantels into opulent displays.',
                'features' => [
                    'Faceted Crystal Sphere Stem: Highlighted by a clear, faceted crystal orb stem that captures and refracts light from every angle.',
                    'Dazzling Rhinestone Trim: Encircling bands of pave-set crystal rhinestones adorn both the rim of the glass cup holder and the solid base.',
                    'Amber Glass Hurricane Sleeve: A smooth amber glass cylinder diffuses live flame flicker into a soft, cozy, golden glow.',
                    'Weighted Mirror-Finish Base: Built on a broad, polished gold pedestal base to ensure balance, safety, and modern appeal.',
                ],
                'specs'    => [
                    'Finish: Polished Metallic Gold with Clear Crystals',
                    'Style: High Glam / Modern Luxury / Art Deco',
                    'Package Includes: 1 x Gold Crystal Orb Pedestal Stand, 1 x Amber Glass Insert',
                ],
                'care'     => [
                    'Wipe metal, crystal orb, and rhinestone surfaces gently with a soft microfiber cloth.',
                    'Clean the amber glass insert with glass cleaner once completely cool.',
                    'Avoid abrasive sponges or harsh chemical polishes to maintain the gold finish and stone brilliance.',
                ],
            ],
            [
                'sku'      => 'RSM-CS-010',
                'name'     => 'Dual Regal Crystal Candle Stand',
                'slug'     => 'dual-regal-crystal-candle-stand',
                'image'    => 'dual-regal-crystal-candle-stand.jpg',
                'material' => 'Polished Metallic Gold with Clear Crystals',
                'short'    => 'Elevate your tabletop décor with the magnificent Dual Regal Crystal Candle Stand.',
                'overview' => 'Elevate your tabletop décor with the magnificent Dual Regal Crystal Candle Stand. Designed with an elongated stem featuring two stacked, faceted clear crystal spheres, this luxury candle holder offers captivating brilliance and vertical drama. Detailed with dense rhinestone pavé bands around the glass collar and solid base, it is crowned by an amber glass hurricane sleeve that casts a soothing, golden light across dining setups and mantels.',
                'features' => [
                    'Stacked Crystal Orb Stem: Features two stacked, precision-faceted crystal spheres along the central pillar that catch and refract surrounding light.',
                    'Shimmering Rhinestone Details: Encircling bands of pave-set crystal rhinestones trim both the glass cup collar and the weighted base for a sparkling accent.',
                    'Amber Glass Hurricane Sleeve: A tall, smooth amber glass cylinder shields candle flames while softening light into a warm glow.',
                    'Statuesque Pedestal Base: Built on a weighted, polished gold round base with a mirror finish, ensuring balance and grandeur.',
                ],
                'specs'    => [
                    'Finish: Polished Metallic Gold with Clear Crystals',
                    'Style: High Glam / Art Deco / Modern Luxury',
                    'Package Includes: 1 x Elevated Twin Crystal Orb Gold Stand, 1 x Amber Glass Insert',
                ],
                'care'     => [
                    'Gently dust crystal spheres, metal stem, and rhinestone trim with a dry, soft microfiber cloth.',
                    'Clean the amber glass insert with a mild glass cleaner once cool.',
                    'Avoid harsh chemical polishes or abrasive pads to preserve the polished gold finish and stone brilliance.',
                ],
            ],
            [
                'sku'      => 'RSM-CS-011',
                'name'     => 'Trio Regal Crystal Candle Stand',
                'slug'     => 'trio-regal-crystal-candle-stand',
                'image'    => 'trio-regal-crystal-candle-stand.jpg',
                'material' => 'Polished Metallic Gold with Clear Crystals',
                'short'    => 'Bring dramatic height and opulent sparkle to your home with Trio Regal Crystal Candle Stand.',
                'overview' => 'Bring dramatic height and opulent sparkle to your home with Trio Regal Crystal Candle Stand. Crafted with a tall pedestal stem featuring three stacked, faceted clear crystal spheres, this statement candle stand captures and refracts ambient light from every angle. Highlighted by dense rhinestone pavé bands wrapping around the glass collar and solid base, it is crowned with a smooth amber glass hurricane sleeve that diffuses live flames into a luxurious, warm glow.',
                'features' => [
                    'Stacked Trio-Orb Pillar: Built with three faceted crystal spheres arranged along an elongated central column for maximum vertical drama and reflection.',
                    'Encrusted Rhinestone Trim: Detailed with high-density, multi-row rhinestone pavé bands along both the upper cup holder rim and the sturdy base.',
                    'Warm Amber Hurricane Cylinder: A tall, heat-resistant amber glass sleeve shields flickering candle flames while softening light for an intimate ambiance.',
                    'Weighted Mirror-Finish Base: Supported by a broad, polished gold circular base engineered for total stability on dining tables, mantels, or entryway consoles.',
                ],
                'specs'    => [
                    'Finish: Polished Metallic Gold with Clear Crystals',
                    'Style: High Glam / Art Deco / Grand Luxury',
                    'Package Includes: 1 x Tall Triple Crystal Orb Gold Stand, 1 x Amber Glass Hurricane Sleeve',
                ],
                'care'     => [
                    'Gently wipe the crystal spheres, metal stem, and rhinestone details with a soft, lint-free microfiber cloth.',
                    'Clean the amber glass sleeve with a mild glass cleaner once completely cool.',
                    'Avoid harsh chemical polishes or abrasive pads to protect the metallic gold finish and crystal clarity.',
                ],
            ],
            [
                'sku'      => 'RSM-CS-012',
                'name'     => 'Opulenza Crystal Flora Candle Stand',
                'slug'     => 'opulenza-crystal-flora-candle-stand',
                'image'    => 'opulenza-crystal-flora-candle-stand.jpg',
                'material' => 'High-Shine Metallic Gold with Clear Crystal',
                'short'    => 'Bring a touch of botanical elegance and sparkling charm to your living space with Opulenza Crystal Flora Candle Stand.',
                'overview' => 'Bring a touch of botanical elegance and sparkling charm to your living space with Opulenza Crystal Flora Candle Stand. Highlighted by a prominent, six-petaled crystal blossom at the base of the glass, this piece refracts light brilliantly while cradling a warm amber glass hurricane cylinder. Elevated on a sleek, polished gold stem and contoured pedestal, it instantly becomes a romantic centerpiece for dining tables, sideboards, and festive decor.',
                'features' => [
                    'Faceted Crystal Flower Accent: Features a striking, hand-assembled six-petal crystal daisy that catches natural sunlight and candlelight alike.',
                    'Amber Glass Hurricane Core: A tall, heat-resistant amber glass cylinder diffuses live flames into a soft, inviting golden glow.',
                    'Polished Gilded Pedestal: Crafted with a smooth, mirror-finish gold column accented by a central metallic orb and a wide, weighted round base for total stability.',
                    'Versatile Decor Display: Perfectly sized to hold votive candles, tea lights, or small LED pillar candles.',
                ],
                'specs'    => [
                    'Finish: High-Shine Metallic Gold with Clear Crystal',
                    'Style: Romantic Luxury / Modern Glam / Botanical',
                    'Package Includes: 1 x Crystal Flower Gold Stand, 1 x Amber Glass Insert',
                ],
                'care'     => [
                    'Wipe metal stem, base, and crystal petals gently with a dry, lint-free microfiber cloth.',
                    'Clean the amber glass insert with mild glass cleaner once completely cool.',
                    'Avoid using abrasive pads or harsh chemical polishes to protect the lustrous gold finish.',
                ],
            ],
            [
                'sku'      => 'RSM-CS-013',
                'name'     => 'Lumina Blossom Candle Stand',
                'slug'     => 'lumina-blossom-candle-stand',
                'image'    => 'lumina-blossom-candle-stand.jpg',
                'material' => 'High-Shine Metallic Gold with Clear Crystal',
                'short'    => 'Elevate your home accent collection with Lumina Blossom Candle Stand.',
                'overview' => 'Elevate your home accent collection with Lumina Blossom Candle Stand. Featuring an elongated, sleek gold column, this version raises the striking six-petaled crystal flower to create commanding vertical height on any surface. Nestled above the sparkling faceted flower is a warm amber glass hurricane sleeve that diffuses flickering candlelight into a soft, inviting, and radiant glow.',
                'features' => [
                    'Statuesque Elevated Silhouette: Designed with a tall, polished gold pillar stem that brings dramatic height and luxury appeal to dining tables, mantels, or console arrangements.',
                    'Faceted Crystal Flower Centerpiece: A prominent six-petal crystal blossom sits directly beneath the glass cup, beautifully catching natural sunlight and candlelight alike.',
                    'Warm Amber Glass Hurricane: A tall, heat-resistant amber glass cylinder shields live flames while softening light for an intimate ambiance.',
                    'Stable Pedestal Base: Supported by a contoured, weighted gold base with a central metallic orb accent to ensure total stability.',
                ],
                'specs'    => [
                    'Finish: High-Shine Metallic Gold with Clear Crystal',
                    'Style: Romantic Luxury / Modern Glam / Botanical',
                    'Package Includes: 1 x Elevated Crystal Flower Gold Stand, 1 x Amber Glass Hurricane Insert',
                ],
                'care'     => [
                    'Gently wipe the metal column, base, and crystal petals with a soft, dry microfiber cloth.',
                    'Clean the amber glass cylinder with a mild glass cleaner once completely cool.',
                    'Avoid using abrasive pads or harsh chemical polishes to maintain the lustrous gold finish.',
                ],
            ],
            [
                'sku'      => 'RSM-CS-014',
                'name'     => 'Floral Crystal Candle Stand',
                'slug'     => 'floral-crystal-candle-stand',
                'image'    => 'floral-crystal-candle-stand.jpg',
                'material' => 'High-Shine Metallic Gold with Clear Crystals',
                'short'    => 'Bring commanding height and refined botanical splendour to your decor with Floral Crystal Candle Stand.',
                'overview' => 'Bring commanding height and refined botanical splendour to your decor with Floral Crystal Candle Stand. Featuring an extra-tall, sleek gold column, this piece elevates a brilliant six-petaled crystal blossom to eye level, catching light with striking brilliance. Nestled above the crystal motif sits a clear amber glass hurricane cylinder that diffuses flickering candle flame into a warm, inviting glow-making it a grand centerpiece for dining tables, entrance consoles, or mantel displays.',
                'features' => [
                    'Grand Pillar Elevation: Engineered with an extended, high-polish gold stem that creates dramatic vertical height and luxury presence.',
                    'Multifaceted Crystal Flower Motif: A six-petal crystal blossom anchors the glass hurricane cup, reflecting ambient light from every angle.',
                    'Amber Glass Hurricane Sleeve: A smooth amber-tinted glass cylinder shields live flames while outputting a soft, warm lighting effect.',
                    'Weighted Mirror-Finish Pedestal: Supported by a broad, contoured gold base with a central metallic sphere detail for maximum balance and stability.',
                ],
                'specs'    => [
                    'Finish: High-Shine Metallic Gold with Clear Crystals',
                    'Style: High Glam / Botanical Luxury / Modern Classic',
                    'Package Includes: 1 x Extra-Tall Crystal Flower Gold Stand, 1 x Amber Glass Hurricane Insert',
                ],
                'care'     => [
                    'Gently wipe the metal column, base, and crystal flower with a soft, lint-free microfiber cloth.',
                    'Clean the amber glass insert with a mild glass cleaner once completely cool.',
                    'Avoid abrasive sponges or harsh chemical polishes to maintain the lustrous gold finish.',
                ],
            ],
        ];
    }
}
