<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The collections landing page at /collection.
 *
 * A `pages` row with the `collections` template, so it edits under Pages with
 * the same editor as About and Contact rather than needing a screen of its own.
 *
 * Idempotent: a page already on this template is left exactly as the shop has
 * it.
 *
 * No occasions are ticked on purpose. Empty means ALL of them, so a fresh
 * install shows every live occasion and its products without anyone opening the
 * editor — and ticking some later narrows it.
 */
class CollectionsPageSeeder extends Seeder
{
    public function run(): void
    {
        $now  = date('Y-m-d H:i:s');
        $data = <<<'JSON'
{"hero": {"eyebrow": "Collection III · Festive", "title": "A season, wrapped in linen.", "intro": "From the first Diwali diya to the last Karwa Chauth thali — the year, one festival at a time.", "aside": "A festival is the year remembering itself.", "image": ""}, "ethos": {"eyebrow": "The ethos", "title": "Every festival, a small edition.", "paragraphs": [{"body": "Each festival gets its own limited edition — composed once, for one season, then quietly retired."}, {"body": "Diwali arrives in velvet, Rakhi in silk thread, Holi in coloured linen. Every edition carries its own hand-pressed seal."}, {"body": "When it is gone, it is gone. That, too, is part of the ritual."}]}, "tiles": {"eyebrow": "By festival", "title": "Every celebration, considered.", "occasions": []}, "edit": {"eyebrow": "The pieces", "title": "The Festive Edit.", "link_label": "View all", "source": []}, "feature": {"eyebrow": "This season", "title": "Diwali — the Velvet Edit.", "body": "Our Diwali edition arrives in deep burgundy velvet, lined with silk. Twelve hand-cast diyas, eight traditional mithai, and a single gold wax seal.", "image": "", "cta_label": "Read the journal", "cta_link": "/shop"}, "invite": {"title": "Enter our world, unhurried.", "body": "Ritual notes, seasonal editions and quiet invitations delivered no more than twice a month.", "form_title": "Let us craft something special", "whatsapp": ""}}
JSON;

        /*
         * An occasion with no products makes the rail on this page empty, and
         * an empty rail reads as a broken section rather than an unfilled one.
         *
         * Only occasions that have NONE are touched, so a shop that has curated
         * its own is never overwritten.
         */
        foreach ($this->db->table('collections')->where('is_active', 1)->get()->getResultArray() as $collection) {
            $has = $this->db->table('collection_products')
                ->where('collection_id', $collection['id'])->countAllResults();

            if ($has > 0) {
                continue;
            }

            $products = $this->db->table('products')->select('id')
                ->where('is_active', 1)->where('deleted_at', null)
                // Offset by the collection's own id, so the three do not all
                // show the same eight pieces.
                ->orderBy('id', 'ASC')->limit(8, ((int) $collection['id'] - 1) * 8)
                ->get()->getResultArray();

            foreach ($products as $i => $product) {
                $this->db->table('collection_products')->ignore(true)->insert([
                    'collection_id' => (int) $collection['id'],
                    'product_id'    => (int) $product['id'],
                    'sort_order'    => $i * 10,
                    'created_at'    => $now,
                ]);
            }
        }

        $existing = $this->db->table('pages')->where('template', 'collections')->get()->getRowArray();

        if ($existing !== null) {
            echo "  Collections page: already set up.\n";

            return;
        }

        // An old plain "Collections" page is UPGRADED rather than duplicated.
        $old = $this->db->table('pages')->where('slug', 'collections')->get()->getRowArray();

        if ($old !== null) {
            $this->db->table('pages')->where('id', $old['id'])->update([
                'template'   => 'collections',
                'data'       => $data,
                'updated_at' => $now,
            ]);

            echo "  Collections page: upgraded.\n";

            return;
        }

        $this->db->table('pages')->insert([
            'title'            => 'Collections',
            'slug'             => 'collections',
            'template'         => 'collections',
            'excerpt'          => 'Every celebration, considered.',
            'content'          => '',
            'data'             => $data,
            'show_in_footer'   => 0,
            'sort_order'       => 5,
            'is_active'        => 1,
            'meta_title'       => 'Collections',
            'meta_description' => 'From the first Diwali diya to the last Karwa Chauth thali — '
                . 'the year, one festival at a time.',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        echo "  Collections page: created.\n";
    }
}
