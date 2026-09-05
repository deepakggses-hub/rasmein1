<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\BannerModel;
use App\Models\SettingModel;
use App\Models\TestimonialModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Everything that appears on the homepage, in one place.
 *
 * The copy was previously scattered through the generic Settings list, where a
 * shop owner had to know that `home_philosophy_body` was the paragraph under
 * the second heading. Grouping it here — beside the hero slides, testimonials
 * and gallery it sits with — is the difference between a settings table and a
 * screen someone can actually use.
 *
 * Behind its own permission, because "edit the homepage" and "edit the mail
 * server" are not the same job.
 */
class Homepage extends AdminController
{
    /** Copy blocks, in the order they appear on the page. */
    private const SECTIONS = [
        'Hero and philosophy' => [
            // The promise strip wording. Copy, so it belongs here; the on/off
            // switch stays under Appearance with the other layout controls.
            'design_marquee_text',

            'home_philosophy_kicker', 'home_philosophy_title', 'home_philosophy_body',
        ],
        'Featured collections' => [
            'home_collections_kicker', 'home_collections_title',
        ],
        'Best sellers' => [
            'home_edit_kicker', 'home_edit_title',
        ],
        'Shop by occasion' => [
            'home_occasion_kicker', 'home_occasion_title', 'home_occasion_label',
        ],
        'Testimonials' => [
            'home_reviews_kicker', 'home_reviews_title',
        ],
        'Clients and gallery' => [
            'home_clients_title', 'home_gallery_title',
        ],
        'Sign-up' => [
            'home_signup_title', 'home_signup_body',
        ],
    ];

    public function index()
    {
        if ($denied = $this->deny('homepage.manage')) {
            return $denied;
        }

        $model    = model(SettingModel::class);
        $settings = [];

        /*
         * By KEY, not by group.
         *
         * `design_marquee_text` is copy shown on the homepage but lives in the
         * `design` group beside its on/off switch. Fetching by group meant this
         * screen never found it: the field rendered blank, saving wrote the
         * value correctly, and the next page load showed blank again — so it
         * looked like nothing was being stored at all.
         *
         * SECTIONS is the list of keys this screen owns, so it is also the
         * right thing to query on.
         */
        $keys = array_merge(...array_values(self::SECTIONS));

        foreach ($model->whereIn('key_name', $keys)->findAll() as $row) {
            $settings[$row['key_name']] = $row;
        }

        $banners = model(BannerModel::class);

        return $this->adminPage('admin/homepage/index', [
            'sections'     => self::SECTIONS,
            'settings'     => $settings,
            'missing'      => count(array_diff(
                array_merge(...array_values(self::SECTIONS)),
                array_keys($settings)
            )),
            'testimonials' => model(TestimonialModel::class)
                ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll(),
            // Counts, so the screen can say what is missing rather than only
            // linking somewhere hopefully useful.
            'slots'        => [
                'home_hero'    => $banners->where('position', 'home_hero')->countAllResults(),
                'home_feature' => $banners->where('position', 'home_feature')->countAllResults(),
                'home_client'  => $banners->where('position', 'home_client')->countAllResults(),
                'home_gallery' => $banners->where('position', 'home_gallery')->countAllResults(),
            ],
            'positions'    => config(\Config\Rasmein::class)->bannerPositions,
        ], 'Homepage');
    }

    public function save()
    {
        if ($denied = $this->deny('homepage.manage')) {
            return $denied;
        }

        $keys = array_merge(...array_values(self::SECTIONS));

        foreach ($keys as $key) {
            $value = $this->request->getPost($key);

            if ($value === null) {
                continue;
            }

            /*
             * Keep a key in the group it already belongs to.
             *
             * `design_marquee_text` lives in `design` beside its on/off switch.
             * Forcing every key to `home` on save would move it out from under
             * that switch — the storefront would still read it, but Appearance
             * would no longer show the pair together.
             *
             * A key that does not exist yet is created in `home`, which is the
             * right home for new copy on this screen.
             */
            $existing = model(SettingModel::class)->where('key_name', $key)->first();
            $group    = $existing['group_name'] ?? 'home';

            // Blank is meaningful here: it HIDES the section. So it is stored
            // as written rather than skipped.
            $this->settings->set($key, mb_substr(trim((string) $value), 0, 4000), 'string', $group);
        }

        $this->settings->flush();
        service('audit')->log('homepage_updated', 'content', 'setting', null, 'Homepage copy updated');

        return redirect()->to(site_url('admin/homepage'))->with('success', 'Homepage copy saved.');
    }

    /** Install any copy setting the code expects but the database lacks. */
    public function restore()
    {
        if ($denied = $this->deny('homepage.manage')) {
            return $denied;
        }

        try {
            ob_start();
            \Config\Database::seeder()->call('HomeContentSeeder');
            ob_end_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            log_message('error', 'Homepage restore failed: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not install the defaults — see the log.');
        }

        $this->settings->flush();

        return redirect()->to(site_url('admin/homepage'))->with('success', 'Missing homepage settings installed.');
    }

    // =================================================================
    // Testimonials
    // =================================================================

    public function testimonial(?int $id = null)
    {
        if ($denied = $this->deny('homepage.manage')) {
            return $denied;
        }

        $quote = null;

        if ($id !== null) {
            $quote = model(TestimonialModel::class)->find($id);

            if ($quote === null) {
                throw PageNotFoundException::forPageNotFound();
            }
        }

        return $this->adminPage('admin/homepage/testimonial', [
            'quote' => $quote,
        ], $quote === null ? 'New testimonial' : 'Edit testimonial');
    }

    public function saveTestimonial(?int $id = null)
    {
        if ($denied = $this->deny('homepage.manage')) {
            return $denied;
        }

        $model = model(TestimonialModel::class);

        if ($id !== null && $model->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $payload = [
            'quote'      => trim((string) $this->request->getPost('quote')),
            'author'     => trim((string) $this->request->getPost('author')),
            'role'       => trim((string) $this->request->getPost('role')) ?: null,
            'rating'     => max(1, min(5, (int) $this->request->getPost('rating'))),
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'is_active'  => $this->request->getPost('is_active') !== null ? 1 : 0,
        ];

        $image = $this->request->getFile('image');

        if ($image !== null && $image->getError() !== UPLOAD_ERR_NO_FILE) {
            $result = service('images')->store($image, 'products', 400);

            if ($result['ok']) {
                $payload['image'] = $result['path'];
            }
        }

        // {id} placeholders are filled from the payload, not update()'s first
        // argument — see CLAUDE.md.
        if ($id !== null) {
            $payload['id'] = $id;
        }

        $saved = $id === null ? $model->insert($payload) : $model->update($id, $payload);

        if ($saved === false) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        service('audit')->log(
            $id === null ? 'created' : 'updated',
            'content',
            'testimonial',
            $id ?? (int) $model->getInsertID(),
            $payload['author']
        );

        return redirect()->to(site_url('admin/homepage'))->with('success', 'Testimonial saved.');
    }

    public function deleteTestimonial(int $id)
    {
        if ($denied = $this->deny('homepage.manage')) {
            return $denied;
        }

        $model = model(TestimonialModel::class);
        $quote = $model->find($id);

        if ($quote === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->delete($id);
        service('audit')->log('deleted', 'content', 'testimonial', $id, $quote['author']);

        return redirect()->to(site_url('admin/homepage'))->with('success', 'Testimonial removed.');
    }
}
