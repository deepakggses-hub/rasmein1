<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Rasmein;

/**
 * Runtime settings. Safe to re-run: existing keys keep their current value so
 * a re-seed never silently flips a live store back to a default.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ---------------------------------------- the master switch
            [
                'group_name'  => 'journey',
                'key_name'    => 'journey_mode',
                'value'       => Rasmein::MODE_BUY,
                'value_type'  => 'string',
                'label'       => 'Site journey',
                'description' => 'Buy now takes payment online. Enquire now captures every basket as a lead instead. Applies to the whole store; individual products can override it.',
                'is_public'   => 1,
                'is_locked'   => 1,
                'sort_order'  => 1,
            ],
            [
                'group_name'  => 'journey',
                'key_name'    => 'enquiry_notify_emails',
                'value'       => json_encode(['sales@rasmein.com']),
                'value_type'  => 'json',
                'label'       => 'Notify these addresses about new enquiries',
                'is_public'   => 0,
                'sort_order'  => 2,
            ],

            // ------------------------------------------------- store
            ['group_name' => 'store', 'key_name' => 'store_name', 'value' => 'Rasmein', 'value_type' => 'string', 'label' => 'Store name', 'is_public' => 1, 'sort_order' => 1],
            ['group_name' => 'store', 'key_name' => 'store_tagline', 'value' => 'Gifting that carries a feeling.', 'value_type' => 'string', 'label' => 'Tagline', 'is_public' => 1, 'sort_order' => 2],
            ['group_name' => 'store', 'key_name' => 'support_email', 'value' => 'hello@rasmein.com', 'value_type' => 'string', 'label' => 'Support email', 'is_public' => 1, 'sort_order' => 3],
            ['group_name' => 'store', 'key_name' => 'support_phone', 'value' => '+91 98765 43210', 'value_type' => 'string', 'label' => 'Support phone', 'is_public' => 1, 'sort_order' => 4],
            ['group_name' => 'store', 'key_name' => 'whatsapp_number', 'value' => '', 'value_type' => 'string', 'label' => 'WhatsApp number', 'is_public' => 1, 'sort_order' => 5],
            ['group_name' => 'store', 'key_name' => 'maintenance_mode', 'value' => '0', 'value_type' => 'bool', 'label' => 'Maintenance mode', 'is_public' => 0, 'is_locked' => 1, 'sort_order' => 6],

            // ---------------------------------------------- checkout
            ['group_name' => 'checkout', 'key_name' => 'guest_checkout_enabled', 'value' => '1', 'value_type' => 'bool', 'label' => 'Allow guest checkout', 'sort_order' => 1],
            ['group_name' => 'checkout', 'key_name' => 'free_shipping_threshold', 'value' => '1500.00', 'value_type' => 'decimal', 'label' => 'Free delivery above', 'is_public' => 1, 'sort_order' => 2],
            ['group_name' => 'checkout', 'key_name' => 'shipping_flat_rate', 'value' => '79.00', 'value_type' => 'decimal', 'label' => 'Flat delivery charge', 'is_public' => 1, 'sort_order' => 3],
            ['group_name' => 'checkout', 'key_name' => 'tax_enabled', 'value' => '0', 'value_type' => 'bool', 'label' => 'Apply tax at checkout', 'sort_order' => 4],
            ['group_name' => 'checkout', 'key_name' => 'tax_percent', 'value' => '0.00', 'value_type' => 'decimal', 'label' => 'Tax percentage', 'sort_order' => 5],
            ['group_name' => 'checkout', 'key_name' => 'max_cart_items', 'value' => '50', 'value_type' => 'int', 'label' => 'Maximum lines per cart', 'sort_order' => 6],
            ['group_name' => 'checkout', 'key_name' => 'coupons_enabled', 'value' => '1', 'value_type' => 'bool', 'label' => 'Accept coupon codes', 'sort_order' => 7],

            // ---------------------------------------------- gifting
            ['group_name' => 'gifting', 'key_name' => 'gift_message_enabled', 'value' => '1', 'value_type' => 'bool', 'label' => 'Offer a gift message', 'is_public' => 1, 'sort_order' => 1],
            ['group_name' => 'gifting', 'key_name' => 'gift_message_max_chars', 'value' => '300', 'value_type' => 'int', 'label' => 'Gift message character limit', 'is_public' => 1, 'sort_order' => 2],

            // ---------------------------------------------- payments
            // Off for now. The gateway phase flips this and fills the keys in .env.
            ['group_name' => 'payments', 'key_name' => 'payment_enabled', 'value' => '0', 'value_type' => 'bool', 'label' => 'Accept online payment', 'is_locked' => 1, 'sort_order' => 1],
            ['group_name' => 'payments', 'key_name' => 'payment_gateway', 'value' => 'razorpay', 'value_type' => 'string', 'label' => 'Payment gateway', 'is_locked' => 1, 'sort_order' => 2],
            ['group_name' => 'payments', 'key_name' => 'cod_enabled', 'value' => '0', 'value_type' => 'bool', 'label' => 'Allow cash on delivery', 'sort_order' => 3],

            // ------------------------------------------------ social
            ['group_name' => 'social', 'key_name' => 'social_instagram', 'value' => '', 'value_type' => 'string', 'label' => 'Instagram URL', 'is_public' => 1, 'sort_order' => 1],
            ['group_name' => 'social', 'key_name' => 'social_facebook', 'value' => '', 'value_type' => 'string', 'label' => 'Facebook URL', 'is_public' => 1, 'sort_order' => 2],
        ];

        $table = $this->db->table('settings');
        $now   = date('Y-m-d H:i:s');
        $added = 0;

        foreach ($settings as $setting) {
            $exists = $this->db->table('settings')
                ->where('key_name', $setting['key_name'])
                ->countAllResults() > 0;

            if ($exists) {
                continue;
            }

            $table->insert(array_merge([
                'is_public'  => 0,
                'is_locked'  => 0,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ], $setting));

            $added++;
        }

        cache()->delete('rasmein_settings');

        echo "  Settings: {$added} added, " . (count($settings) - $added) . " already present.\n";
    }
}
