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

    /*
     * Only logout survives here.
     *
     * Signing in and registering moved to Storefront\Auth, which uses one-time
     * codes and Google. The password actions that used to live in this file are
     * gone rather than merely unrouted: an unreachable password login sitting
     * beside a passwordless one is an invitation to wire it back up, and that
     * would reopen exactly what this change closed.
     */

    public function logout()
    {
        session()->remove(['customer_id', 'customer_name', 'customer_email']);
        session()->regenerate(true);

        return redirect()->to(site_url('/'))->with('success', 'Signed out.');
    }
}
