<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The templates the code sends. Idempotent: an existing key is left alone, so
 * re-seeding never overwrites wording an admin has edited.
 */
class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $orderTokens = [
            'order_ref'      => 'The order reference, e.g. RSM-2026-000123',
            'customer_name'  => "The customer's name",
            'customer_email' => 'Their email address',
            'customer_phone' => 'Their phone number',
            'order_total'    => 'Grand total, formatted',
            'order_subtotal' => 'Subtotal before delivery',
            'order_status'   => 'Current status',
            'placed_at'      => 'Date the order was placed',
            'order_url'      => 'Link to the order confirmation page',
            'admin_url'      => 'Link to the order in the admin panel',
            'ship_name'      => 'Recipient name',
            'ship_address'   => 'Full delivery address on one line',
        ];

        $dispatchTokens = $orderTokens + [
            'courier_name'    => 'Courier the parcel went with',
            'tracking_number' => 'Tracking number',
            'tracking_url'    => 'Link to the courier tracking page',
        ];

        $templates = [
            [
                'template_key' => 'order_placed_customer',
                'name'         => 'Order placed — to the customer',
                'description'  => 'Sent the moment an order is submitted.',
                'audience'     => 'customer',
                'subject'      => 'Your {{brand_name}} order {{order_ref}}',
                'placeholders' => $orderTokens,
                'body'         => '<p>Thank you, {{customer_name}}.</p>'
                    . '<p>We have your order <strong>{{order_ref}}</strong>, placed on {{placed_at}}, '
                    . 'for a total of <strong>{{order_total}}</strong>.</p>'
                    . '<p>We pack every box by hand and dispatch within 48 hours. You will get a '
                    . 'tracking link as soon as it leaves us.</p>'
                    . '<p><a href="{{order_url}}">View your order</a></p>'
                    . '<p>Any questions, reply to this email and quote {{order_ref}}.</p>',
            ],
            [
                'template_key' => 'order_placed_admin',
                'name'         => 'Order placed — to the team',
                'description'  => 'Alerts staff that an order needs confirming.',
                'audience'     => 'admin',
                'subject'      => 'New order {{order_ref}} — {{order_total}}',
                'placeholders' => $orderTokens,
                'body'         => '<p><strong>{{order_ref}}</strong> · {{order_total}}</p>'
                    . '<p>{{customer_name}} — {{customer_email}} — {{customer_phone}}</p>'
                    . '<p>Deliver to: {{ship_name}}, {{ship_address}}</p>'
                    . '<p><a href="{{admin_url}}">Open in the admin panel</a></p>',
            ],
            [
                'template_key' => 'order_confirmed_customer',
                'name'         => 'Order confirmed — to the customer',
                'description'  => 'Sent when staff move an order to Confirmed.',
                'audience'     => 'customer',
                'subject'      => '{{order_ref}} is confirmed',
                'placeholders' => $orderTokens,
                'body'         => '<p>Good news, {{customer_name}} — {{order_ref}} is confirmed and '
                    . 'we are packing it now.</p><p><a href="{{order_url}}">Track your order</a></p>',
            ],
            [
                'template_key' => 'order_dispatched_customer',
                'name'         => 'Order dispatched — to the customer',
                'description'  => 'Sent when a tracking number is recorded.',
                'audience'     => 'customer',
                'subject'      => '{{order_ref}} is on its way',
                'placeholders' => $dispatchTokens,
                'body'         => '<p>{{customer_name}}, your box has left us.</p>'
                    . '<p>Courier: <strong>{{courier_name}}</strong><br>'
                    . 'Tracking: <strong>{{tracking_number}}</strong></p>'
                    . '<p><a href="{{tracking_url}}">Track the parcel</a></p>'
                    . '<p>Going to: {{ship_name}}, {{ship_address}}</p>',
            ],
            [
                'template_key' => 'order_delivered_customer',
                'name'         => 'Order delivered — to the customer',
                'description'  => 'Sent when an order is marked delivered.',
                'audience'     => 'customer',
                'subject'      => '{{order_ref}} has arrived',
                'placeholders' => $orderTokens,
                'body'         => '<p>{{customer_name}}, {{order_ref}} has been delivered.</p>'
                    . '<p>We hope it lands well. If anything is not right, reply to this email '
                    . 'within 48 hours and we will put it right.</p>',
            ],
            [
                'template_key' => 'order_cancelled_customer',
                'name'         => 'Order cancelled — to the customer',
                'description'  => 'Sent when an order is cancelled.',
                'audience'     => 'customer',
                'subject'      => '{{order_ref}} has been cancelled',
                'placeholders' => $orderTokens,
                'body'         => '<p>{{customer_name}}, we have cancelled {{order_ref}}.</p>'
                    . '<p>If a payment was taken it will be returned to the original method '
                    . 'within 5–7 working days. Reply to this email if anything looks wrong.</p>',
            ],
            [
                'template_key' => 'enquiry_received_customer',
                'name'         => 'Enquiry received — to the customer',
                'description'  => 'Acknowledges an enquiry and sets expectations.',
                'audience'     => 'customer',
                'subject'      => 'We have your enquiry — {{order_ref}}',
                'placeholders' => $orderTokens,
                'body'         => '<p>Thank you, {{customer_name}}.</p>'
                    . '<p>Your enquiry <strong>{{order_ref}}</strong> is with us. A person will read '
                    . 'it properly and come back with a written quote, usually within one working day.</p>'
                    . '<p>Nothing has been charged. The indicative figure was {{order_total}}; your '
                    . 'quote will confirm the real price.</p>',
            ],
            [
                'template_key' => 'enquiry_received_admin',
                'name'         => 'Enquiry received — to the team',
                'description'  => 'Alerts staff that a lead is waiting.',
                'audience'     => 'admin',
                'subject'      => 'New enquiry {{order_ref}} from {{customer_name}}',
                'placeholders' => $orderTokens,
                'body'         => '<p><strong>{{order_ref}}</strong> · indicative {{order_total}}</p>'
                    . '<p>{{customer_name}} — {{customer_email}} — {{customer_phone}}</p>'
                    . '<p><a href="{{admin_url}}">Open the enquiry</a></p>'
                    . '<p>Aim to reply within one working day.</p>',
            ],
            [
                'template_key' => 'customer_welcome',
                'name'         => 'Welcome — to the customer',
                'description'  => 'Sent when someone creates an account.',
                'audience'     => 'customer',
                'subject'      => 'Welcome to {{brand_name}}',
                'placeholders' => ['customer_name' => "The customer's name"],
                'body'         => '<p>Welcome, {{customer_name}}.</p>'
                    . '<p>Your account keeps your addresses and order history to hand, so reordering '
                    . 'takes a moment.</p><p><a href="{{site_url}}/account">Go to your account</a></p>',
            ],
            [
                'template_key' => 'customer_password_reset',
                'name'         => 'Password reset — to the customer',
                'description'  => 'Carries the reset link. Keep the link in this template.',
                'audience'     => 'customer',
                'subject'      => 'Reset your {{brand_name}} password',
                'placeholders' => [
                    'customer_name' => "The customer's name",
                    'reset_url'     => 'The single-use reset link',
                    'expiry_hours'  => 'How many hours the link lasts',
                ],
                'body'         => '<p>{{customer_name}}, someone asked to reset the password on this '
                    . 'account.</p><p><a href="{{reset_url}}">Choose a new password</a></p>'
                    . '<p>The link works once and lasts {{expiry_hours}} hour(s). If this was not you, '
                    . 'ignore this email — nothing has changed.</p>',
            ],
            [
                'template_key' => 'customer_register_attempt',
                'name'         => 'Registration attempt — to the customer',
                'description'  => 'Sent when someone tries to register with an address that already has an account.',
                'audience'     => 'customer',
                'subject'      => 'Someone tried to register with your email',
                'placeholders' => ['customer_name' => "The customer's name"],
                'body'         => '<p>{{customer_name}}, someone just tried to create a {{brand_name}} '
                    . 'account with this address. You already have one, so nothing was created.</p>'
                    . '<p>If that was you, <a href="{{site_url}}/account/login">sign in</a> — or '
                    . '<a href="{{site_url}}/account/forgot">reset your password</a> if you have '
                    . 'forgotten it. If it was not you, no action is needed.</p>',
            ],
        ];

        $added = 0;
        $now   = date('Y-m-d H:i:s');

        foreach ($templates as $template) {
            if ($this->db->table('email_templates')->where('template_key', $template['template_key'])->countAllResults() > 0) {
                continue;
            }

            $this->db->table('email_templates')->insert([
                'template_key' => $template['template_key'],
                'name'         => $template['name'],
                'description'  => $template['description'],
                'audience'     => $template['audience'],
                'subject'      => $template['subject'],
                'body_html'    => $template['body'],
                'placeholders' => json_encode($template['placeholders'], JSON_UNESCAPED_UNICODE),
                'is_system'    => 1,
                'is_active'    => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            $added++;
        }

        echo "  Email templates: {$added} added (" . count($templates) . " defined).\n";
    }
}
