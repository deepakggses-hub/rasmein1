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
            [
                'template_key' => 'order_processing_customer',
                'name'         => 'Order being packed — to the customer',
                'description'  => 'Sent when an order moves to Processing.',
                'audience'     => 'customer',
                'subject'      => 'We are packing {{order_ref}}',
                'placeholders' => $orderTokens,
                'body'         => '<p>{{customer_name}}, your box is being packed by hand now.</p>'
                    . '<p>We will email a tracking link the moment it leaves us.</p>',
            ],
            [
                'template_key' => 'order_packed_customer',
                'name'         => 'Order packed — to the customer',
                'description'  => 'Sent when an order is packed and waiting for the courier.',
                'audience'     => 'customer',
                'subject'      => '{{order_ref}} is packed and ready',
                'placeholders' => $orderTokens,
                'body'         => '<p>{{customer_name}}, {{order_ref}} is boxed, sealed and waiting '
                    . 'for the courier. Tracking follows shortly.</p>',
            ],
            [
                'template_key' => 'order_refunded_customer',
                'name'         => 'Order refunded — to the customer',
                'description'  => 'Sent when an order is marked refunded.',
                'audience'     => 'customer',
                'subject'      => 'Your refund for {{order_ref}}',
                'placeholders' => $orderTokens,
                'body'         => '<p>{{customer_name}}, we have refunded {{order_total}} against '
                    . '{{order_ref}}.</p><p>It should reach your original payment method within '
                    . '5–7 working days. Reply to this email if it has not arrived by then.</p>',
            ],
            [
                'template_key' => 'enquiry_quoted_customer',
                'name'         => 'Quote ready — to the customer',
                'description'  => 'Sent when staff move an enquiry to Quoted.',
                'audience'     => 'customer',
                'subject'      => 'Your quote for {{order_ref}}',
                'placeholders' => [
                    'order_ref'     => 'The enquiry reference',
                    'customer_name' => "The customer's name",
                    'quoted_value'  => 'The quoted figure, formatted',
                ],
                'body'         => '<p>{{customer_name}}, thank you for your patience.</p>'
                    . '<p>We have quoted <strong>{{quoted_value}}</strong> for {{order_ref}}. '
                    . 'The full breakdown follows in a separate note from the person handling '
                    . 'your enquiry.</p><p>The quote holds for 15 days. Reply to this email to '
                    . 'go ahead, or with any changes you would like.</p>',
            ],
            [
                'template_key' => 'customer_password_changed',
                'name'         => 'Password changed — to the customer',
                'description'  => 'Security notice sent after a password is reset or changed.',
                'audience'     => 'customer',
                'subject'      => 'Your {{brand_name}} password was changed',
                'placeholders' => [
                    'customer_name' => "The customer's name",
                    'changed_at'    => 'When the change happened',
                ],
                'body'         => '<p>{{customer_name}}, the password on your account was changed '
                    . 'on {{changed_at}}.</p><p>If that was you, nothing more is needed.</p>'
                    . '<p><strong>If it was not you</strong>, reply to this email straight away '
                    . 'and we will secure the account.</p>',
            ],
            [
                'template_key' => 'staff_welcome_admin',
                'name'         => 'Staff account created — to the new member',
                'description'  => 'Sent when an administrator creates an account. Never contains a password.',
                'audience'     => 'admin',
                'subject'      => 'Your {{brand_name}} admin account',
                'placeholders' => [
                    'staff_name' => 'The new member\'s name',
                    'login_url'  => 'The admin sign-in address',
                ],
                'body'         => '<p>{{staff_name}}, an admin account has been created for you '
                    . 'at {{brand_name}}.</p><p><a href="{{login_url}}">Sign in here</a>. Whoever '
                    . 'set it up will give you the starting password separately — you will be '
                    . 'asked to replace it the first time you sign in.</p>'
                    . '<p>We never send passwords by email.</p>',
            ],
            [
                'template_key' => 'admin_password_changed',
                'name'         => 'Staff password changed — security notice',
                'description'  => 'Sent to a staff member when their password changes.',
                'audience'     => 'admin',
                'subject'      => 'Your {{brand_name}} admin password was changed',
                'placeholders' => [
                    'staff_name' => 'The staff member\'s name',
                    'changed_at' => 'When the change happened',
                ],
                'body'         => '<p>{{staff_name}}, the password on your admin account changed '
                    . 'on {{changed_at}}.</p><p>If that was not you, tell whoever manages the '
                    . 'shop immediately — an admin account has access to orders and customer '
                    . 'details.</p>',
            ],
            [
                'template_key' => 'low_stock_digest_admin',
                'name'         => 'Low stock digest — to the team',
                'description'  => 'One daily summary of everything running low, rather than an email per product.',
                'audience'     => 'admin',
                'subject'      => '{{product_count}} product(s) running low',
                'placeholders' => [
                    'product_count' => 'How many products are low',
                    'product_list'  => 'One product per line, with SKU and quantity',
                    'admin_url'     => 'Link to the filtered product list',
                ],
                'body'         => '<p>{{product_count}} product(s) are at or below their low-stock '
                    . 'threshold:</p><p>{{product_list}}</p>'
                    . '<p><a href="{{admin_url}}">Review them in the admin panel</a></p>',
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
