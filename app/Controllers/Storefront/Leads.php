<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use CodeIgniter\Controller;

/**
 * The enquiry form on a content page.
 *
 * Lands in the same `enquiries` table the checkout pipeline uses, so a lead
 * from the story page appears in the admin beside every other one rather than
 * in an inbox somebody has to remember to read.
 */
class Leads extends Controller
{
    public function submit()
    {
        $post = $this->request->getPost();

        $name  = trim((string) ($post['name'] ?? ''));
        $email = strtolower(trim((string) ($post['email'] ?? '')));
        $phone = preg_replace('/\D/', '', (string) ($post['phone'] ?? '')) ?? '';

        /*
         * A honeypot would be better, but this form is public and unauthenticated
         * so the cheap checks matter: a name, a plausible email and a plausible
         * number. Anything failing goes back with the input intact rather than
         * being silently dropped — a real customer must not lose what they typed.
         */
        $errors = [];

        if ($name === '') {
            $errors[] = 'Tell us your name.';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'That email address does not look right.';
        }

        if (strlen($phone) < 10 || strlen($phone) > 15) {
            $errors[] = 'Enter a phone number we can reach you on.';
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('error', implode(' ', $errors));
        }

        // Rate limited by IP: a public form with no account behind it is an
        // open invitation otherwise.
        $ip  = $this->request->getIPAddress();
        $key = 'lead_' . md5($ip);

        if ((int) (cache($key) ?? 0) >= 5) {
            return redirect()->back()->withInput()
                ->with('error', 'You have sent several enquiries already. We will be in touch shortly.');
        }

        cache()->save($key, (int) (cache($key) ?? 0) + 1, 3600);

        try {
            db_connect()->table('leads')->insert([
                'ref'        => 'ENQ-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'source'     => mb_substr((string) ($post['source'] ?? 'website'), 0, 40),
                'status'     => 'new',
                'name'       => mb_substr($name, 0, 120),
                'email'      => mb_substr($email, 0, 191),
                'phone'      => mb_substr($phone, 0, 20),
                'occasion'   => mb_substr(trim((string) ($post['occasion'] ?? '')), 0, 120) ?: null,
                'quantity'   => (int) ($post['quantity'] ?? 0) ?: null,
                'budget'     => mb_substr(trim((string) ($post['budget'] ?? '')), 0, 60) ?: null,
                'message'    => mb_substr(trim((string) ($post['message'] ?? '')), 0, 4000) ?: null,
                'ip_address' => $ip,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Lead capture failed: {m}', ['m' => $e->getMessage()]);

            return redirect()->back()->withInput()
                ->with('error', 'That could not be sent just now. Please write to us directly.');
        }

        // Tell the shop. A lead nobody is told about is a lead nobody answers.
        try {
            service('mail')->queue('enquiry_received_admin', service('brand')->supportEmail, [
                'customer_name' => $name,
                'order_ref'     => $post['source'] ?? 'website',
            ]);
        } catch (\Throwable $e) {
            // Never let a mail failure lose the lead — it is already saved.
            log_message('error', 'Lead notification failed: {m}', ['m' => $e->getMessage()]);
        }

        return redirect()->back()->with(
            'success',
            'Thank you — we have your brief and will come back with a considered proposal.'
        );
    }
}
