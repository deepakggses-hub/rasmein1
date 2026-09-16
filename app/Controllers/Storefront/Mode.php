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
         * The switch chooses a JOURNEY, so it goes to that journey's home.
         *
         * Returning to the page they were on cannot work here: a corporate page
         * sets the mode back on when it loads, so switching to personalised on
         * /corporate turned the cookie off and then straight back on — the
         * switch looked broken because the destination undid it.
         *
         * Corporate on -> the corporate page. Corporate off -> the homepage,
         * which is the ordinary shop's front door and sets the mode to match.
         */
        if ($mode === Rasmein::MODE_ENQUIRE) {
            $page = model(\App\Models\PageModel::class)
                ->where('template', 'corporate')->where('is_active', 1)->first();

            if ($page !== null) {
                return redirect()->to(site_url('corporate'));
            }
        } else {
            return redirect()->to(site_url());
        }

        /*
         * No message.
         *
         * The switch itself already shows which journey is on, and the
         * destination page is unmistakably one or the other. A toast saying so
         * is a second announcement of something the reader can see.
         */
        /*
         * Unreachable in practice — both branches above return — but a bare
         * fall-through onto an undefined variable would be a 500 the day
         * someone edits one of them.
         */
        return redirect()->to(site_url());
    }
}
