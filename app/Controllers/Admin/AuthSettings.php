<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * The sign-in screen: its wording, and the Google credentials behind it.
 *
 * These need a screen of their own because the client SECRET must be stored
 * encrypted. A generic settings text field would write it in plain text, which
 * is why the two Google keys were locked — and a locked setting with nowhere to
 * edit it is worse than an unlocked one, because it simply cannot be set at all.
 * That was the state until this screen existed.
 */
class AuthSettings extends AdminController
{
    /** The wording, in the order it appears on screen. */
    private const COPY = [
        // Header controls, which live in the same group.
        'search_placeholders',
        'corporate_banner', 'corporate_on_message', 'corporate_off_message',

        'auth_login_title', 'auth_login_body',
        'auth_register_title', 'auth_register_body',
        'auth_panel_eyebrow', 'auth_panel_title', 'auth_panel_body',
        'auth_code_title', 'auth_code_body',
        'auth_details_title', 'auth_details_body',
    ];

    public function index()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $values = [];

        foreach (db_connect()->table('settings')
            ->whereIn('key_name', array_merge(self::COPY, ['google_auth_client_id']))
            ->get()->getResultArray() as $row) {
            $values[$row['key_name']] = ['value' => $row['value'], 'label' => $row['label']];
        }

        return $this->adminPage('admin/auth/index', [
            'copy'         => self::COPY,
            'values'       => $values,
            // The secret itself is NEVER sent to the browser, only whether one
            // is set. Rendering it into a form field would put it in every
            // proxy log and browser cache between here and the screen.
            'hasSecret'    => trim((string) service('settings')->get('google_auth_client_secret', '')) !== '',
            'redirectUri'  => service('googleAuth')->redirectUri(),
            'configured'   => service('googleAuth')->isConfigured(),
        ], 'Sign-in screen');
    }

    public function save()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $settings = service('settings');

        foreach (self::COPY as $key) {
            $posted = $this->request->getPost($key);

            if ($posted === null) {
                continue;
            }

            $settings->set($key, trim((string) $posted), 'string', 'auth');
        }

        $clientId = trim((string) $this->request->getPost('google_auth_client_id'));
        $settings->set('google_auth_client_id', $clientId, 'string', 'auth');

        $secret = trim((string) $this->request->getPost('google_auth_client_secret'));

        if ($secret !== '') {
            /*
             * Encrypted at rest, and only written when a new one is typed —
             * a blank field means "leave it alone", not "clear it". Without
             * that, opening this screen and saving the copy would wipe the
             * secret every time.
             */
            try {
                $settings->set(
                    'google_auth_client_secret',
                    base64_encode(service('encrypter')->encrypt($secret)),
                    'string',
                    'auth'
                );
            } catch (\Throwable $e) {
                log_message('error', 'Google secret could not be encrypted: {m}', ['m' => $e->getMessage()]);

                return redirect()->back()->with('error', 'The secret could not be stored securely. Nothing was saved.');
            }
        }

        if ($this->request->getPost('forget_secret') !== null) {
            $settings->set('google_auth_client_secret', '', 'string', 'auth');
        }

        // The secret is never in the audit trail, only the fact it changed.
        service('audit')->log('updated', 'settings', 'auth', null, 'Sign-in screen'
            . ($secret !== '' ? ' (Google secret replaced)' : ''));

        return redirect()->back()->with('success', 'Saved.');
    }
}
