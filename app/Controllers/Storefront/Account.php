<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\CustomerModel;
use App\Models\LoginAttemptModel;
use App\Models\PasswordResetModel;
use Config\Rasmein;

/**
 * Customer sign-in, registration and password reset.
 *
 * The recurring theme is NOT LEAKING WHO HAS AN ACCOUNT. A shop's customer list
 * is commercially sensitive and personally sensitive both — someone should not
 * be able to discover that an address shops here by trying it in a form. So:
 *
 *  - sign-in gives one message for every kind of failure;
 *  - registration with an existing address does not say "already registered" —
 *    it reports success and sends a "someone tried to register" email instead;
 *  - password reset always reports the same thing, whether or not the address
 *    exists.
 *
 * These are not behind the customerAuth filter — they are how you get past it.
 */
class Account extends StorefrontController
{
    // =================================================================
    // Sign in
    // =================================================================

    public function showLogin()
    {
        if (session('customer_id') !== null) {
            return redirect()->to(site_url('account'));
        }

        return $this->page('storefront/account/login', [
            'crumbs' => [['label' => 'Sign in', 'url' => null]],
        ], ['title' => 'Sign in · ' . $this->brand->brandName, 'noindex' => true]);
    }

    public function login()
    {
        $email    = strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');
        $ip       = $this->request->getIPAddress();

        if ($email === '' || $password === '') {
            return redirect()->back()->withInput()->with('error', 'Enter your email and password.');
        }

        $throttler = service('throttler');
        $key       = 'customer_login_' . hash('sha256', $ip . '|' . $email);

        if ($throttler->check($key, config(Rasmein::class)->loginAttemptsPerMinute, MINUTE) === false) {
            model(LoginAttemptModel::class)->record('customer', $email, false);

            return redirect()->back()->withInput()->with(
                'error',
                'Too many attempts. Try again in ' . max(1, $throttler->getTokenTime()) . ' seconds.'
            );
        }

        $customers = model(CustomerModel::class);
        $customer  = $customers->findByEmail($email);

        // password_verify runs either way, so the response time does not reveal
        // whether the address exists.
        $hash  = $customer['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
        $valid = password_verify($password, (string) $hash)
            && $customer !== null
            && $customer['password_hash'] !== null
            && (int) $customer['is_active'] === 1;

        if (! $valid) {
            model(LoginAttemptModel::class)->record('customer', $email, false);

            return redirect()->back()->withInput()->with('error', 'Those details are not correct.');
        }

        if (password_needs_rehash((string) $hash, PASSWORD_DEFAULT)) {
            $customers->update($customer['id'], ['password_hash' => $customers->hashPassword($password)]);
        }

        return $this->establishSession($customer, 'Welcome back.');
    }

    public function logout()
    {
        session()->remove(['customer_id', 'customer_name', 'customer_email']);
        session()->regenerate(true);

        return redirect()->to(site_url('/'))->with('success', 'Signed out.');
    }

    // =================================================================
    // Register
    // =================================================================

    public function showRegister()
    {
        if (session('customer_id') !== null) {
            return redirect()->to(site_url('account'));
        }

        return $this->page('storefront/account/register', [
            'crumbs' => [['label' => 'Create an account', 'url' => null]],
        ], ['title' => 'Create an account · ' . $this->brand->brandName, 'noindex' => true]);
    }

    public function register()
    {
        // Honeypot — a person never sees this field.
        if (trim((string) $this->request->getPost('website')) !== '') {
            return redirect()->to(site_url('account/login'))->with('success', 'Account created. You can sign in now.');
        }

        $rules = [
            'name'     => ['label' => 'Name', 'rules' => 'required|min_length[2]|max_length[120]'],
            'email'    => ['label' => 'Email', 'rules' => 'required|valid_email|max_length[191]'],
            'phone'    => ['label' => 'Phone', 'rules' => 'permit_empty|min_length[10]|max_length[20]'],
            'password' => ['label' => 'Password', 'rules' => 'required|min_length[10]|max_length[200]'],
            'password_confirm' => ['label' => 'Confirmation', 'rules' => 'required|matches[password]'],
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $customers = model(CustomerModel::class);
        $email     = strtolower(trim((string) $this->request->getPost('email')));
        $existing  = $customers->findByEmail($email);

        if ($existing !== null) {
            // Do NOT say the address is taken — that turns this form into an
            // account-existence oracle. Report the same success either way and
            // let the real owner find out by email.
            service('notify')->registerAttempt($email, (string) $existing['name']);

            return redirect()->to(site_url('account/login'))
                ->with('success', 'Account created. You can sign in now.');
        }

        $id = $customers->insert([
            'name'             => trim((string) $this->request->getPost('name')),
            'email'            => $email,
            'phone'            => trim((string) $this->request->getPost('phone')) ?: null,
            'password_hash'    => $customers->hashPassword((string) $this->request->getPost('password')),
            'marketing_opt_in' => $this->request->getPost('marketing_opt_in') !== null ? 1 : 0,
            'is_active'        => 1,
        ], true);

        if ($id === false || $id === null) {
            return redirect()->back()->withInput()->with('errors', $customers->errors());
        }

        service('notify')->customerWelcome($customers->find((int) $id));

        return $this->establishSession($customers->find((int) $id), 'Account created.');
    }

    // =================================================================
    // Password reset
    // =================================================================

    public function showForgot()
    {
        return $this->page('storefront/account/forgot', [
            'crumbs' => [['label' => 'Reset your password', 'url' => null]],
        ], ['title' => 'Reset your password · ' . $this->brand->brandName, 'noindex' => true]);
    }

    public function sendReset()
    {
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $ip    = $this->request->getIPAddress();

        // Rate limited per IP, so this cannot be used to enumerate addresses in
        // bulk or to mail-bomb someone.
        if (service('throttler')->check('pwreset_' . hash('sha256', $ip), 5, HOUR) === false) {
            return redirect()->back()->with('error', 'Too many requests. Try again later.');
        }

        $customer = $email !== '' ? model(CustomerModel::class)->findByEmail($email) : null;

        if ($customer !== null && (int) $customer['is_active'] === 1) {
            $token = model(PasswordResetModel::class)
                ->issue('customer', (int) $customer['id'], $email, $ip);

            $link = site_url('account/reset/' . $token);
            service('notify')->passwordReset($email, (string) $customer['name'], $link);

            // In development the link also goes to the log, so the flow is
            // testable before SMTP exists. Never in production.
            if (ENVIRONMENT !== 'production') {
                log_message('info', 'Password reset link for {email}: {url}', ['email' => $email, 'url' => $link]);
            }
        }

        // Identical response either way.
        return redirect()->to(site_url('account/login'))->with(
            'success',
            'If that address has an account, a reset link is on its way. The link lasts one hour.'
        );
    }

    public function showReset(string $token)
    {
        return $this->page('storefront/account/reset', [
            'token'  => $token,
            'crumbs' => [['label' => 'Choose a new password', 'url' => null]],
        ], ['title' => 'Choose a new password · ' . $this->brand->brandName, 'noindex' => true]);
    }

    public function doReset()
    {
        $token = (string) $this->request->getPost('token');

        $rules = [
            'password'         => ['label' => 'Password', 'rules' => 'required|min_length[10]|max_length[200]'],
            'password_confirm' => ['label' => 'Confirmation', 'rules' => 'required|matches[password]'],
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        // consume() burns the token before we act on it, so a double submit
        // cannot reset twice.
        $reset = model(PasswordResetModel::class)->consume($token);

        if ($reset === null) {
            return redirect()->to(site_url('account/forgot'))
                ->with('error', 'That link has expired or been used. Request a new one.');
        }

        $customers = model(CustomerModel::class);
        $customer  = $customers->find((int) $reset['user_id']);

        if ($customer === null) {
            return redirect()->to(site_url('account/forgot'))->with('error', 'That link is no longer valid.');
        }

        $customers->update($customer['id'], [
            'password_hash' => $customers->hashPassword((string) $this->request->getPost('password')),
        ]);

        // Anyone holding an old session for this account is signed out with it.
        session()->regenerate(true);

        // A security notice, not a welcome. Getting this wrong once meant a
        // customer resetting a password received "Welcome to Rasmein".
        service('mail')->queue('customer_password_changed', (string) $customer['email'], [
            'customer_name' => $customer['name'],
            'changed_at'    => date('j M Y, H:i'),
        ], (int) $customer['id'], 'customer');

        return redirect()->to(site_url('account/login'))
            ->with('success', 'Password updated. Sign in with it now.');
    }

    // =================================================================
    // Helpers
    // =================================================================

    /** @param array<string, mixed> $customer */
    private function establishSession(array $customer, string $message)
    {
        // Against session fixation.
        session()->regenerate(true);

        session()->set([
            'customer_id'    => (int) $customer['id'],
            'customer_name'  => $customer['name'],
            'customer_email' => $customer['email'],
        ]);

        model(CustomerModel::class)->update($customer['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        model(LoginAttemptModel::class)->record('customer', (string) $customer['email'], true);

        // A basket filled before signing in must not be lost.
        service('cart')->attachToCustomer((int) $customer['id']);

        $intended = session()->getFlashdata('intended_url');

        return redirect()->to($intended ?? site_url('account'))->with('success', $message);
    }

}
