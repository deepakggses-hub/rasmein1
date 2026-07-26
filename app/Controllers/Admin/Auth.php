<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AdminUserModel;
use App\Models\LoginAttemptModel;
use Config\Rasmein;

/**
 * Staff sign-in.
 *
 * Four things this deliberately does (CLAUDE.md §5):
 *  - one generic failure message, so the form cannot be used to discover which
 *    email addresses have accounts;
 *  - rate limiting per IP+identifier, with the remaining wait stated plainly;
 *  - session regeneration on success, against session fixation;
 *  - rehash on login when the cost factor has moved on.
 *
 * This controller is NOT behind AdminAuthFilter — it is how you get past it.
 * It still extends AdminController, because the password screen renders inside
 * the admin layout and that layout needs the chrome (nav, current user, journey
 * mode) that only adminPage() assembles. AdminController tolerates a null user,
 * so the sign-in actions are unaffected.
 */
class Auth extends AdminController
{
    public function showLogin()
    {
        if (session('admin_id') !== null) {
            return redirect()->to(site_url('admin'));
        }

        return view('admin/auth/login', [
            'brand' => $this->brand,
        ]);
    }

    public function login()
    {
        $email    = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');
        $ip       = $this->request->getIPAddress();

        if ($email === '' || $password === '') {
            return redirect()->back()->withInput()->with('error', 'Enter your email and password.');
        }

        // Throttle on IP + identifier together: one attacker cannot lock out a
        // real user, and one user's mistakes do not block the office.
        $throttler = service('throttler');
        $key       = 'admin_login_' . hash('sha256', $ip . '|' . strtolower($email));
        $perMinute = config(Rasmein::class)->loginAttemptsPerMinute;

        if ($throttler->check($key, $perMinute, MINUTE) === false) {
            $wait = $throttler->getTokenTime();

            model(LoginAttemptModel::class)->record('admin', $email, false);

            return redirect()->back()->withInput()->with(
                'error',
                'Too many attempts. Try again in ' . max(1, $wait) . ' second'
                    . ($wait === 1 ? '' : 's') . '.'
            );
        }

        $users = model(AdminUserModel::class);
        $user  = $users->findActiveByEmail($email);

        // Same message whether the account does not exist, is inactive, or the
        // password is wrong. And password_verify runs either way so the response
        // time does not give the answer away.
        $hash  = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
        $valid = password_verify($password, $hash) && $user !== null;

        if (! $valid) {
            model(LoginAttemptModel::class)->record('admin', $email, false);
            log_message('warning', 'Failed admin sign-in for {email} from {ip}', ['email' => $email, 'ip' => $ip]);

            return redirect()->back()->withInput()->with('error', 'Those details are not correct.');
        }

        // Upgrade the stored hash if the cost factor has changed since it was set.
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $users->update($user['id'], ['password_hash' => $users->hashPassword($password)]);
        }

        // Against session fixation.
        session()->regenerate(true);

        $full = $users->withRole((int) $user['id']);

        session()->set([
            'admin_id'          => (int) $user['id'],
            'admin_name'        => $full['name'],
            'admin_role'        => $full['role_slug'],
            'admin_permissions' => $full['permissions'],
        ]);

        $users->recordLogin((int) $user['id'], $ip);
        model(LoginAttemptModel::class)->record('admin', $email, true);
        service('audit')->log('signed_in', 'auth', 'admin_user', (int) $user['id'], $full['name'] . ' signed in');

        if ((int) $user['must_change_password'] === 1) {
            return redirect()->to(site_url('admin/password'))
                ->with('success', 'Set a password of your own before you continue.');
        }

        $intended = session()->getFlashdata('intended_url');

        return redirect()->to($intended ?? site_url('admin'))
            ->with('success', 'Signed in.');
    }

    public function logout()
    {
        $id = session('admin_id');

        if ($id !== null) {
            service('audit')->log('signed_out', 'auth', 'admin_user', (int) $id);
        }

        session()->remove(['admin_id', 'admin_name', 'admin_role', 'admin_permissions']);
        session()->regenerate(true);

        return redirect()->to(site_url('admin/login'))->with('success', 'Signed out.');
    }

    // ------------------------------------------------------------ password

    public function showPassword(): string
    {
        $user = model(AdminUserModel::class)->find((int) session('admin_id'));

        return $this->adminPage('admin/auth/password', [
            'forced' => (int) ($user['must_change_password'] ?? 0) === 1,
        ], 'Change password');
    }

    public function updatePassword()
    {
        $rules = [
            'current_password' => ['label' => 'Current password', 'rules' => 'required'],
            'new_password'     => [
                'label' => 'New password',
                'rules' => 'required|min_length[10]|max_length[200]',
            ],
            'confirm_password' => [
                'label' => 'Confirmation',
                'rules' => 'required|matches[new_password]',
            ],
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $users = model(AdminUserModel::class);
        $user  = $users->find((int) session('admin_id'));

        if ($user === null || ! password_verify((string) $this->request->getPost('current_password'), $user['password_hash'])) {
            return redirect()->back()->with('error', 'Your current password is not correct.');
        }

        $new = (string) $this->request->getPost('new_password');

        // A short blocklist of the passwords that actually get used.
        //
        // Compared by EQUALITY against a letters-only, lowercased form — not by
        // substring. A substring test rejected "ANewLongerPassword2026", which
        // is a perfectly good password that merely contains the word; that was
        // reported from the field. Reducing to letters still catches the real
        // pattern, which is "password123" and "Rasmein2026".
        $blocked = [
            'password', 'passwords', 'admin', 'administrator', 'rasmein',
            'letmein', 'welcome', 'qwerty', 'qwertyuiop', 'iloveyou',
            'changeme', 'secret', 'login', 'abc',
        ];

        $reduced = strtolower((string) preg_replace('/[^a-z]/i', '', $new));

        if ($reduced === '' || in_array($reduced, $blocked, true)) {
            return redirect()->back()->with(
                'error',
                'That is too close to a password everyone tries. Add some words of your own.'
            );
        }

        $users->update($user['id'], [
            'password_hash'        => $users->hashPassword($new),
            'must_change_password' => 0,
        ]);

        // The hash is never logged, only the fact of the change.
        service('audit')->log('password_changed', 'auth', 'admin_user', (int) $user['id']);
        service('notify')->adminPasswordChanged($users->find((int) $user['id']));

        return redirect()->to(site_url('admin'))->with('success', 'Password updated.');
    }
}
