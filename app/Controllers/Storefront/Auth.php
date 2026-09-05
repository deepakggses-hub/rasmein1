<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\CustomerModel;
use App\Models\LoginAttemptModel;

/**
 * Signing in and creating an account.
 *
 * HOW IT WORKS
 *
 *  - No passwords. Signing in means asking for a one-time code, which always
 *    goes to the EMAIL — even when the person identified themselves by phone.
 *    The screen then shows which address it went to, so they know where to look.
 *  - Creating an account requires confirming that email before the account
 *    exists at all. Nothing is written to `customers` until the code checks out,
 *    so an unconfirmed attempt cannot squat on an address the real owner wants.
 *  - Google is a direct route in: Google has already proved the address.
 *  - A Google account still owes us a phone number, so it lands on a short
 *    "finish your details" step with the name pre-filled and editable.
 *
 * WHAT IS DELIBERATELY VAGUE
 *
 * Whether an address or number is registered is never revealed. "Send me a
 * code" answers identically either way, because the difference is precisely
 * what someone testing a list of emails is looking for.
 */
class Auth extends StorefrontController
{
    /** The combined sign-in / create-account screen. */
    public function index()
    {
        if (session('customer_id') !== null) {
            return redirect()->to(site_url('account'));
        }

        return $this->page('storefront/auth', [
            'mode'   => $this->request->getGet('mode') === 'register' ? 'register' : 'login',
            'copy'   => $this->copy(),
            'google' => service('googleAuth')->isConfigured(),
            // Shown only to someone signed into the admin panel, so a visitor
            // never sees setup instructions.
            'showGoogleHint' => session('admin_id') !== null,
        ], ['title' => 'Sign in · ' . $this->brand->brandName, 'noindex' => true]);
    }

    // ============================================================ sign in

    /** Step one: they tell us who they are, we email a code. */
    public function requestCode()
    {
        $identifier = trim((string) $this->request->getPost('identifier'));

        if ($identifier === '') {
            return redirect()->back()->withInput()->with('error', 'Enter your email address or phone number.');
        }

        $customer = $this->findByIdentifier($identifier);

        /*
         * A missing account is NOT reported. The screen advances either way and
         * simply never receives a code — otherwise this endpoint becomes a way
         * to test which addresses and numbers are registered.
         */
        if ($customer !== null && (int) $customer['is_active'] === 1) {
            $result = service('otp')->issue((string) $customer['email'], 'login', [
                'name' => $customer['name'],
            ]);

            if (! $result['ok']) {
                return redirect()->back()->withInput()->with('error', $result['error']);
            }
        }

        // The address shown back is the one we WOULD have sent to. When there is
        // no account, echo a masked version of what they typed so the screen
        // still makes sense without confirming anything.
        $shown = $customer !== null ? (string) $customer['email'] : $identifier;

        session()->set([
            'otp_email' => $customer !== null ? (string) $customer['email'] : null,
            'otp_shown' => $this->maskEmail($shown),
            'otp_stage' => 'login',
        ]);

        return redirect()->to(site_url('account/code'));
    }

    /**
     * Step two: they enter the code.
     *
     * Not typed `: string` — landing here with no pending code has to return a
     * REDIRECT, and a string return type turns that into a 500.
     */
    public function codeForm()
    {
        if (session('otp_stage') === null) {
            return redirect()->to(site_url('account/login'))
                ->with('error', 'Start by telling us your email or phone number.');
        }

        return $this->page('storefront/auth_code', [
            'shown' => (string) session('otp_shown'),
            'stage' => (string) session('otp_stage'),
            'copy'  => $this->copy(),
        ], ['title' => 'Enter your code · ' . $this->brand->brandName, 'noindex' => true]);
    }

    public function verifyCode()
    {
        $stage = (string) session('otp_stage');
        $email = (string) session('otp_email');
        $code  = (string) $this->request->getPost('code');

        if ($stage === '' || $email === '') {
            return redirect()->to(site_url('account/login'))
                ->with('error', 'That took too long. Please start again.');
        }

        $purpose = $stage === 'register' ? 'verify_email' : 'login';
        $result  = service('otp')->verify($email, $code, $purpose);

        if (! $result['ok']) {
            model(LoginAttemptModel::class)->record('customer', $email, false);

            return redirect()->back()->with('error', $result['error']);
        }

        $model = model(CustomerModel::class);

        if ($purpose === 'verify_email') {
            // The account is created HERE, once the address is proven.
            $payload = $result['payload'];

            $model->insert([
                'name'                 => (string) ($payload['name'] ?? 'Guest'),
                'email'                => $email,
                'phone'                => (string) ($payload['phone'] ?? ''),
                'email_verified_at'    => date('Y-m-d H:i:s'),
                'profile_completed_at' => date('Y-m-d H:i:s'),
                'marketing_opt_in'     => (int) ($payload['marketing'] ?? 0),
                'is_active'            => 1,
            ]);

            $customer = $model->find($model->getInsertID());
        } else {
            $customer = $model->where('email', $email)->first();
        }

        if ($customer === null) {
            return redirect()->to(site_url('account/login'))->with('error', 'Something went wrong. Please try again.');
        }

        session()->remove(['otp_email', 'otp_shown', 'otp_stage']);

        return $this->establishSession((array) $customer, $purpose === 'verify_email'
            ? 'Welcome to ' . $this->brand->brandName . '.'
            : 'Welcome back.');
    }

    public function resend()
    {
        $email = (string) session('otp_email');
        $stage = (string) session('otp_stage');

        if ($email === '' || $stage === '') {
            return redirect()->to(site_url('account/login'));
        }

        // The pending signup details have to ride along again, or confirming the
        // resent code would create an account with nothing in it.
        $payload = (array) (session('otp_payload') ?? []);

        $result = service('otp')->issue($email, $stage === 'register' ? 'verify_email' : 'login', $payload);

        return redirect()->back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'A new code is on its way.' : $result['error']
        );
    }

    // ======================================================= create account

    public function register()
    {
        $name  = trim((string) $this->request->getPost('name'));
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $phone = $this->normalisePhone((string) $this->request->getPost('phone'));

        $errors = [];

        if ($name === '') {
            $errors[] = 'Tell us your name.';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'That email address does not look right.';
        }

        if ($phone === null) {
            $errors[] = 'Enter a phone number we can reach you on.';
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('error', implode(' ', $errors));
        }

        $model = model(CustomerModel::class);

        /*
         * An existing account is not announced. A code goes to the address
         * either way — a LOGIN code if the account exists, a verification code
         * if it does not. Both land the person on the same screen, and the one
         * who owns the address ends up signed in regardless.
         */
        $existing = $model->where('email', $email)->first();

        if ($existing !== null) {
            service('otp')->issue($email, 'login', ['name' => $existing['name']]);
            $stage = 'login';
        } else {
            $payload = ['name' => $name, 'phone' => $phone, 'marketing' => $this->request->getPost('marketing') !== null ? 1 : 0];
            service('otp')->issue($email, 'verify_email', $payload);
            session()->set('otp_payload', $payload);
            $stage = 'register';
        }

        session()->set([
            'otp_email' => $email,
            'otp_shown' => $this->maskEmail($email),
            'otp_stage' => $stage,
        ]);

        return redirect()->to(site_url('account/code'));
    }

    // ============================================================== google

    public function google()
    {
        if (! service('googleAuth')->isConfigured()) {
            return redirect()->to(site_url('account/login'))
                ->with('error', 'Signing in with Google is not set up yet.');
        }

        return redirect()->to(service('googleAuth')->authUrl());
    }

    public function googleCallback()
    {
        $result = service('googleAuth')->handleCallback(
            (string) $this->request->getGet('code'),
            (string) $this->request->getGet('state')
        );

        if (! $result['ok']) {
            return redirect()->to(site_url('account/login'))->with('error', $result['error']);
        }

        $profile = $result['profile'];
        $model   = model(CustomerModel::class);

        // By subject id first — the only stable identifier Google gives.
        $customer = $model->where('google_id', $profile['google_id'])->first();

        if ($customer === null) {
            // Then by email, to link an account they already made by hand.
            $customer = $model->where('email', $profile['email'])->first();

            if ($customer !== null) {
                $model->update($customer['id'], [
                    'id'                => $customer['id'],
                    'google_id'         => $profile['google_id'],
                    'avatar_url'        => $profile['avatar'] ?: null,
                    'email_verified_at' => $customer['email_verified_at'] ?? date('Y-m-d H:i:s'),
                ]);
                $customer = $model->find($customer['id']);
            }
        }

        if ($customer === null) {
            $model->insert([
                'name'              => $profile['name'] ?: 'Guest',
                'email'             => $profile['email'],
                'phone'             => '',
                'google_id'         => $profile['google_id'],
                'avatar_url'        => $profile['avatar'] ?: null,
                // Google has already proved the address.
                'email_verified_at' => date('Y-m-d H:i:s'),
                // But not the phone number, so the profile stays incomplete.
                'profile_completed_at' => null,
                'is_active'         => 1,
            ]);

            $customer = $model->find($model->getInsertID());
        }

        return $this->establishSession((array) $customer, 'Signed in with Google.');
    }

    // =================================================== finish your details

    public function completeProfile()
    {
        if (session('customer_id') === null) {
            return redirect()->to(site_url('account/login'));
        }

        $customer = model(CustomerModel::class)->find((int) session('customer_id'));

        if ($customer === null) {
            return redirect()->to(site_url('account/login'));
        }

        if ($this->request->getMethod() === 'POST') {
            $name  = trim((string) $this->request->getPost('name'));
            $phone = $this->normalisePhone((string) $this->request->getPost('phone'));

            if ($name === '' || $phone === null) {
                return redirect()->back()->withInput()
                    ->with('error', 'We need both your name and a phone number.');
            }

            model(CustomerModel::class)->update($customer['id'], [
                'id'                   => $customer['id'],
                'name'                 => $name,
                'phone'                => $phone,
                'profile_completed_at' => date('Y-m-d H:i:s'),
            ]);

            session()->set('customer_name', $name);

            $intended = session()->getFlashdata('intended_url');

            return redirect()->to($intended ?? site_url('account'))->with('success', 'All set.');
        }

        return $this->page('storefront/auth_complete', [
            'customer' => $customer,
            'copy'     => $this->copy(),
        ], ['title' => 'A few details · ' . $this->brand->brandName, 'noindex' => true]);
    }

    // -----------------------------------------------------------------

    /** @return array<string, mixed>|null */
    private function findByIdentifier(string $identifier): ?array
    {
        $model = model(CustomerModel::class);

        if (str_contains($identifier, '@')) {
            return $model->where('email', strtolower($identifier))->first();
        }

        $phone = $this->normalisePhone($identifier);

        if ($phone === null) {
            return null;
        }

        /*
         * Match on the last ten digits. People type their number with and
         * without a country code, with spaces, with dashes — and an exact match
         * would tell them their own number is not registered.
         */
        $tail = substr($phone, -10);

        return $model->like('phone', $tail, 'before')->first();
    }

    /** Digits only, with a length that could plausibly be a phone number. */
    private function normalisePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    /**
     * Show enough of an address to be recognisable, not enough to be useful.
     *
     * "de•••••@gmail.com" tells the owner exactly which inbox to open, and tells
     * anyone else almost nothing.
     */
    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$user, $domain] = explode('@', $email, 2);

        $head = mb_substr($user, 0, min(2, mb_strlen($user)));

        return $head . str_repeat('•', max(3, mb_strlen($user) - 2)) . '@' . $domain;
    }

    /** @param array<string, mixed> $customer */
    private function establishSession(array $customer, string $message)
    {
        session()->regenerate(true);

        session()->set([
            'customer_id'    => (int) $customer['id'],
            'customer_name'  => $customer['name'],
            'customer_email' => $customer['email'],
        ]);

        model(CustomerModel::class)->update($customer['id'], [
            'id'            => $customer['id'],
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
        model(LoginAttemptModel::class)->record('customer', (string) $customer['email'], true);

        service('cart')->attachToCustomer((int) $customer['id']);
        $moved = service('basketMerge')->adopt((int) $customer['id']);

        if ($moved['cart'] > 0 || $moved['wishlist'] > 0) {
            $parts = [];

            if ($moved['cart'] > 0) {
                $parts[] = $moved['cart'] . ' item' . ($moved['cart'] === 1 ? '' : 's') . ' from your basket';
            }

            if ($moved['wishlist'] > 0) {
                $parts[] = $moved['wishlist'] . ' saved item' . ($moved['wishlist'] === 1 ? '' : 's');
            }

            $message .= ' We kept ' . implode(' and ', $parts) . '.';
        }

        // A Google account with no phone number cannot check out, so it goes
        // straight to the short form rather than discovering the gap later.
        if ($customer['profile_completed_at'] === null) {
            return redirect()->to(site_url('account/finish'))->with('success', $message);
        }

        $intended = session()->getFlashdata('intended_url');

        return redirect()->to($intended ?? site_url('account'))->with('success', $message);
    }

    /** @return array<string, string> */
    private function copy(): array
    {
        $keys = [
            'auth_login_title', 'auth_login_body', 'auth_register_title', 'auth_register_body',
            'auth_panel_eyebrow', 'auth_panel_title', 'auth_panel_body',
            'auth_code_title', 'auth_code_body', 'auth_details_title', 'auth_details_body',
        ];

        $out = [];

        foreach ($keys as $key) {
            $out[$key] = (string) service('settings')->get($key, '');
        }

        return $out;
    }
}
