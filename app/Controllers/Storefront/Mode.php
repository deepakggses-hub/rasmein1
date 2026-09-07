<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use CodeIgniter\Controller;
use Config\Rasmein;

/**
 * Switching between buying and enquiring.
 *
 * A POST, not a link. Changing how the whole site behaves is a state change,
 * and a GET that does it can be triggered by any image tag on any page.
 */
class Mode extends Controller
{
    public function set()
    {
        $mode = (string) $this->request->getPost('mode');

        if (! in_array($mode, [Rasmein::MODE_BUY, Rasmein::MODE_ENQUIRE], true)) {
            return redirect()->back();
        }

        helper('cookie');

        /*
         * A year, and readable by script — the header reflects the mode without
         * a round trip. It holds no personal data and is not a credential, so
         * httpOnly would buy nothing and cost the immediate switch.
         */
        setcookie(Rasmein::MODE_COOKIE, $mode, [
            'expires'  => time() + 60 * 60 * 24 * 365,
            'path'     => '/',
            'secure'   => $this->request->isSecure(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        $_COOKIE[Rasmein::MODE_COOKIE] = $mode;

        /*
         * Turning corporate ON goes to the corporate page.
         *
         * Switching mode is a statement about WHY someone is here, not a
         * preference to apply to the page they happen to be on — and the
         * corporate page is the answer to "show me what you do for businesses".
         *
         * Only if that page exists: sending them to a redirect-to-shop is worse
         * than leaving them where they were.
         */
        if ($mode === Rasmein::MODE_ENQUIRE) {
            $page = model(\App\Models\PageModel::class)
                ->where('template', 'corporate')->where('is_active', 1)->first();

            if ($page !== null) {
                return redirect()->to(site_url('corporate'))->with(
                    'success',
                    (string) (service('settings')->get('corporate_on_message', '')
                        ?: 'Corporate gifting. Add pieces to an enquiry and we will quote.')
                );
            }
        }

        // Otherwise back where they were, so switching mid-browse does not lose
        // the page.
        $back = (string) ($this->request->getPost('return_to') ?? '');
        $safe = $back !== '' && ! preg_match('#^[a-z]+://#i', $back) && ! str_starts_with($back, '//')
            ? site_url(ltrim($back, '/'))
            : site_url();

        return redirect()->to($safe)->with(
            'success',
            $mode === Rasmein::MODE_ENQUIRE
                ? (string) (service('settings')->get('corporate_on_message', '') ?: 'Corporate gifting. Add pieces to an enquiry and we will quote.')
                : (string) (service('settings')->get('corporate_off_message', '') ?: 'Back to ordinary shopping.')
        );
    }
}
