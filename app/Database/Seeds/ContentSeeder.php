<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Homepage banner and the CMS pages every store needs on day one.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // ------------------------------------------------------- banner
        if ($this->db->table('banners')->where('position', 'home_hero')->countAllResults() === 0) {
            $this->db->table('banners')->insert([
                'eyebrow'    => 'Build your own',
                'title'      => '',   // empty = use the hand-set headline in the view
                'subtitle'   => 'Choose a box, fill each compartment yourself, add a note in your own words. Or send one of our ready hampers as it is.',
                'link_url'   => site_url('build'),
                'cta_label'  => 'Start a box',
                'position'   => 'home_hero',
                'sort_order' => 1,
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            echo "  Banners: 1 hero added.\n";
        }

        // -------------------------------------------------------- pages
        $pages = [
            [
                'title'   => 'About Rasmein',
                'slug'    => 'about-us',
                'excerpt' => 'A gifting studio that treats the box as part of the gift.',
                'footer'  => 1,
                'content' => '<p>Rasmein started with a small irritation: most gift hampers are assembled to a price, not to a person. The cellophane is loud, the filler is generous, and the contents are whatever was in the warehouse.</p>'
                    . '<p>We do it the other way round. You choose the box, then you choose what goes in each compartment. If you would rather not decide, our ready hampers are put together the same way — by someone who would be happy to receive them.</p>'
                    . '<h2>How we choose things</h2>'
                    . '<p>Everything on the site is either made by a small Indian producer we have visited, or sourced from one we buy from repeatedly. Nothing is listed because it had margin in it.</p>'
                    . '<h2>Packing</h2>'
                    . '<p>Boxes are packed by hand the day they ship. We use paper tape, moulded pulp inserts and no plastic filler. The brass ring around each compartment is real brass; it is the one indulgence we allow ourselves.</p>',
            ],
            [
                'title'   => 'Shipping & Delivery',
                'slug'    => 'shipping-and-delivery',
                'excerpt' => 'Where we deliver, how long it takes, and what it costs.',
                'footer'  => 1,
                'content' => '<h2>Dispatch</h2>'
                    . '<p>Orders placed before 2 PM IST on a working day are packed and dispatched within 48 hours. Custom boxes with a gift message take the same time — the message is printed in-house.</p>'
                    . '<h2>Delivery time</h2>'
                    . '<p>Metro cities: 2–4 working days. Other cities and towns: 4–7 working days. Remote pin codes can take a little longer, and we will tell you if yours is one of them before you pay.</p>'
                    . '<h2>Charges</h2>'
                    . '<p>Delivery is free on orders above ₹1,500. Below that a flat ₹79 applies anywhere in India.</p>'
                    . '<h2>Tracking</h2>'
                    . '<p>You will get a tracking link by email and SMS the moment the box leaves us. If a courier marks something delivered and it has not arrived, write to us and we will chase it.</p>',
            ],
            [
                'title'   => 'Returns & Refunds',
                'slug'    => 'returns-and-refunds',
                'excerpt' => 'What we can take back, and what we cannot.',
                'footer'  => 1,
                'content' => '<h2>Damaged or wrong items</h2>'
                    . '<p>If something arrives broken, or is not what you ordered, send us a photo within 48 hours of delivery. We will replace it or refund it — your choice, no return shipping to arrange.</p>'
                    . '<h2>Food and personal care</h2>'
                    . '<p>For safety reasons we cannot accept returns on opened food, tea, or bath and body products. Sealed items in original condition can be returned within 7 days.</p>'
                    . '<h2>Custom boxes</h2>'
                    . '<p>A box you built yourself can be returned if it is unopened, but the gift-message printing is not refundable. Corporate orders are covered by the terms in your quote.</p>'
                    . '<h2>Refund timing</h2>'
                    . '<p>Approved refunds are issued to the original payment method within 5–7 working days of us receiving the item back.</p>',
            ],
            [
                'title'   => 'Corporate Gifting',
                'slug'    => 'corporate-gifting',
                'excerpt' => 'Festive hampers, welcome kits and client boxes, quoted per brief.',
                'footer'  => 1,
                'content' => '<p>We put together gifting for teams of 25 up to a few thousand. Diwali and New Year are our busiest windows, so the earlier you talk to us the more choice you have.</p>'
                    . '<h2>What we can do</h2>'
                    . '<ul>'
                    . '<li>Your logo on the sleeve, the ribbon, or a letterpress card inside</li>'
                    . '<li>A printed note personalised per recipient</li>'
                    . '<li>Delivery to one office, or to individual home addresses across India</li>'
                    . '<li>A physical sample before you commit to the run</li>'
                    . '</ul>'
                    . '<h2>How pricing works</h2>'
                    . '<p>Bulk gifting is always quoted rather than bought off the site — the cost moves with quantity, contents and branding. Send us the brief and we come back with a written quote and a sample cost.</p>'
                    . '<h2>Lead time</h2>'
                    . '<p>Two to three weeks from approved sample for a run of 100. Longer for custom printing or anything over 500 units.</p>',
            ],
            [
                'title'   => 'Privacy Policy',
                'slug'    => 'privacy-policy',
                'excerpt' => 'What we collect, why, and what we never do with it.',
                'footer'  => 1,
                'content' => '<h2>What we collect</h2>'
                    . '<p>Your name, email, phone number and delivery address, so we can send you a box and tell you where it is. If you create an account we also store a hashed version of your password — never the password itself.</p>'
                    . '<h2>Payment details</h2>'
                    . '<p>We never see or store your card details. Payments are handled entirely by our payment gateway; we keep only a transaction reference and whether it succeeded.</p>'
                    . '<h2>What we do not do</h2>'
                    . '<p>We do not sell your data, and we do not share it with anyone beyond the courier and payment partners who need it to complete your order.</p>'
                    . '<h2>Your choices</h2>'
                    . '<p>You can ask us for a copy of your data, or ask us to delete it, by writing to hello@rasmein.com. Marketing email is opt-in and every message has an unsubscribe link.</p>',
            ],
            [
                'title'   => 'Terms of Service',
                'slug'    => 'terms-of-service',
                'excerpt' => 'The agreement between you and Rasmein.',
                'footer'  => 1,
                'content' => '<h2>Orders</h2>'
                    . '<p>An order is confirmed when we accept it, not when it is placed. If something is out of stock or a price is listed in error, we will contact you before charging anything.</p>'
                    . '<h2>Pricing</h2>'
                    . '<p>All prices are in Indian Rupees and include applicable taxes unless stated otherwise at checkout. The total shown at checkout is calculated by us at that moment and is the amount that applies.</p>'
                    . '<h2>Enquiries and quotes</h2>'
                    . '<p>An enquiry is a request, not a purchase. A quote we send is valid for 15 days unless it says otherwise.</p>'
                    . '<h2>Product images</h2>'
                    . '<p>Handmade items — ceramics especially — vary slightly from the photographs. That variation is a feature of the object, not a defect.</p>'
                    . '<h2>Contact</h2>'
                    . '<p>Questions about these terms: hello@rasmein.com.</p>',
            ],
        ];

        $added = 0;

        foreach ($pages as $index => $page) {
            if ($this->db->table('pages')->where('slug', $page['slug'])->countAllResults() > 0) {
                continue;
            }

            $this->db->table('pages')->insert([
                'title'            => $page['title'],
                'slug'             => $page['slug'],
                'excerpt'          => $page['excerpt'],
                'content'          => $page['content'],
                'show_in_footer'   => $page['footer'],
                'sort_order'       => $index + 1,
                'is_active'        => 1,
                'meta_title'       => $page['title'] . ' — Rasmein',
                'meta_description' => $page['excerpt'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            $added++;
        }

        echo "  Pages: {$added} added.\n";
    }
}
