<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\SettingModel;
use Config\Services as AppServices;

/**
 * Shop identity — the things a business must be able to change itself.
 *
 * Everything here previously required editing PHP. Worse, several of these keys
 * already existed in the settings table and were referenced by nothing at all:
 * changing store_name did precisely nothing, because the storefront read
 * Config\Rasmein and that never consulted the database. Services::brand() is
 * the bridge; this screen is the way in.
 *
 * Images go through the same ImageUploadService as everything else — type
 * decided by reading the file, re-encoded through GD, generated filename.
 */
class Brand extends AdminController
{
    /** field => [setting key, upload destination] */
    private const IMAGES = [
        'brand_logo'       => 'Logo',
        'brand_logo_light' => 'Logo for dark backgrounds',
        'brand_favicon'    => 'Favicon',
        'brand_og_image'   => 'Sharing image',
    ];

    private const TEXT = [
        'store_name', 'store_tagline', 'support_email', 'support_phone',
        'whatsapp_number', 'meta_title_suffix', 'meta_description',
        'legal_name', 'legal_gstin', 'legal_address',
        'social_instagram', 'social_facebook', 'social_whatsapp',
        'social_youtube', 'social_pinterest', 'social_linkedin',
    ];

    /** Which settings group a key belongs in when it has to be created. */
    private static function groupFor(string $key): string
    {
        if (str_starts_with($key, 'social_')) {
            return 'social';
        }

        return in_array($key, ['store_name', 'store_tagline', 'support_email', 'support_phone', 'whatsapp_number'], true)
            ? 'store'
            : 'brand';
    }

    /**
     * Install any identity setting the code expects but the database lacks.
     *
     * Same reasoning as the email template restore: these arrive through a
     * seeder, and an install that was migrated but not seeded leaves the screen
     * working yet oddly inert. Safe to press at any time — existing values are
     * untouched.
     */
    public function restore()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $before = model(SettingModel::class)
            ->whereIn('group_name', ['brand', 'store', 'social'])->countAllResults();

        try {
            ob_start();
            \Config\Database::seeder()->call('BrandSettingSeeder');
            ob_end_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            log_message('error', 'Brand settings restore failed: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->back()->with('error', 'The settings could not be restored — see the log.');
        }

        $added = model(SettingModel::class)
            ->whereIn('group_name', ['brand', 'store', 'social'])->countAllResults() - $before;

        $this->settings->flush();

        return redirect()->to(site_url('admin/brand'))->with(
            'success',
            $added === 0 ? 'Everything is already installed.' : $added . ' setting(s) installed.'
        );
    }

    public function index()
    {
        if ($denied = $this->deny('settings.view')) {
            return $denied;
        }

        $values = [];

        foreach (array_merge(self::TEXT, array_keys(self::IMAGES)) as $key) {
            $values[$key] = (string) $this->settings->get($key, '');
        }

        return $this->adminPage('admin/brand/index', [
            'values'    => $values,
            'images'    => self::IMAGES,
            'canManage' => $this->can('settings.manage'),
            // Surfaced so the screen can offer to install what is missing rather
            // than silently doing nothing useful.
            'missing'   => count(array_filter(
                array_merge(self::TEXT, array_keys(self::IMAGES)),
                fn (string $k): bool => model(SettingModel::class)->where('key_name', $k)->countAllResults() === 0
            )),
        ], 'Shop identity');
    }

    public function save()
    {
        if ($denied = $this->deny('settings.manage')) {
            return $denied;
        }

        $model  = model(SettingModel::class);
        $errors = [];

        // ---- text ----
        $email = trim((string) $this->request->getPost('support_email'));

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'The support email address is not valid.';
        }

        foreach (['social_instagram', 'social_facebook', 'social_whatsapp',
            'social_youtube', 'social_pinterest', 'social_linkedin'] as $key) {
            $url = trim((string) $this->request->getPost($key));

            // A social link that is not a link is a broken icon in the footer.
            if ($url !== '' && ! filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = ucfirst(str_replace('social_', '', $key)) . ' must be a full web address, starting https://';
            }
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        foreach (self::TEXT as $key) {
            $value = $this->request->getPost($key);

            if ($value === null) {
                continue;
            }

            $this->settings->set($key, mb_substr(trim((string) $value), 0, 500), 'string', self::groupFor($key));
        }

        // ---- images ----
        $uploadErrors = [];

        foreach (array_keys(self::IMAGES) as $field) {
            // An explicit "remove" is separate from "no new file chosen",
            // otherwise there would be no way to take a logo back off.
            if ($this->request->getPost('remove_' . $field) !== null) {
                service('images')->delete((string) $this->settings->get($field, ''));
                $this->settings->set($field, '', 'string', 'brand');

                continue;
            }

            $file = $this->request->getFile($field);

            if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $result = service('images')->store(
                $file,
                'brand',
                config(\Config\Rasmein::class)->brandImageWidths[$field] ?? 600
            );

            if (! $result['ok']) {
                $uploadErrors[] = self::IMAGES[$field] . ': ' . $result['error'];

                continue;
            }

            // Replace rather than accumulate — an old logo left on disk is
            // clutter nobody will ever go and tidy.
            service('images')->delete((string) $this->settings->get($field, ''));
            $this->settings->set($field, $result['path'], 'string', 'brand');
        }

        $this->settings->flush();

        // The brand service caches its snapshot for the request; the redirect
        // gets a fresh one, but be explicit rather than relying on that.
        AppServices::injectMock('brand', AppServices::brand(false));

        service('audit')->log('brand_updated', 'settings', 'setting', null, 'Shop identity updated');

        return redirect()->to(site_url('admin/brand'))->with(
            $uploadErrors === [] ? 'success' : 'error',
            $uploadErrors === []
                ? 'Shop identity saved.'
                : 'Saved, but some images were refused. ' . implode(' ', $uploadErrors)
        );
    }
}
