<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\BannerModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Rasmein;

/**
 * Banners, grouped by the slot they fill.
 *
 * WHY BY SLOT RATHER THAN ONE LIST
 *
 * A flat list of banners with a position dropdown makes the person adding one
 * work out which slot they want from a name, and gives no sense of what is
 * already there. Each slot behaves differently — a hero is a slide with text, a
 * client logo is a wordmark, a gallery shot is one of many — so each gets its
 * own section, its own guidance, and where it makes sense, a multi-file
 * uploader.
 */
class Banners extends AdminController
{
    /**
     * How each slot behaves.
     *
     * `multi` marks a slot where several images at once is the normal case, so
     * it gets a bulk uploader rather than one form per image.
     *
     * @var array<string, array<string, mixed>>
     */
    private const SLOTS = [
        'corporate_hero' => [
            'key'    => 'corporate',
            'fields' => ['image', 'link', 'text', 'buttons', 'schedule'],
            'label'  => 'Corporate page banner',
            'note'   => 'The slider at the top of /corporate. It behaves exactly like the '
                . 'homepage hero, because it is the same component.',
            'multi'  => true,
            // Same dimensions as the homepage hero: it IS that component.
            'ratio'  => '1920 × 900',
        ],

        'home_hero' => [
            'key'    => 'hero',
            'fields' => ['image', 'link', 'text', 'buttons', 'schedule'],
            'label' => 'Homepage hero',
            'note'  => 'Two or more turn the hero into a slider. Leave the text fields blank '
                . 'and the picture is shown whole with the whole thing linked — use that when '
                . 'the words are already part of the artwork.',
            'multi' => true,
            'ratio' => '1920 × 900',
        ],
        'home_feature' => [
            'key'    => 'feature',
            // No eyebrow: the band draws a title and a description only.
            'fields' => ['image', 'link', 'title', 'buttons', 'schedule'],
            'label' => 'Homepage feature band',
            'note'  => 'The full-width picture midway down the page. Blank text shows the '
                . 'artwork whole rather than cropping it to a band.',
            'multi' => false,
            'ratio' => '1920 × 720',
        ],
        'home_client' => [
            'key'    => 'clients',
            // A logo and a name. The row is not linked, so no link field.
            'fields' => ['image', 'name'],
            'label' => 'Client logos',
            'note'  => 'Upload several at once. A logo image is used when there is one; '
                . 'otherwise the title is set in the display face as a wordmark.',
            'multi' => true,
            'ratio' => 'Transparent PNG, around 400 × 160',
        ],
        'home_gallery' => [
            'key'    => 'gallery',
            // The picture, and nothing else.
            'fields' => ['image'],
            'label' => 'Bespoke journey gallery',
            'note'  => 'The grid of square photographs. Upload the whole set at once.',
            'multi' => true,
            'ratio' => 'Square, around 800 × 800',
        ],
        'home_strip' => [
            'key'    => 'strip',
            'fields' => ['image', 'link', 'text', 'schedule'],
            'label' => 'Homepage strip',
            'note'  => 'Smaller promotional strips.',
            'multi' => false,
            'ratio' => '1600 × 400',
        ],
        'category_top' => [
            'key'    => 'category',
            'fields' => ['image', 'link', 'text', 'schedule'],
            'label' => 'Category page banner',
            'note'  => 'Shown above a category listing.',
            'multi' => false,
            'ratio' => '1920 × 480',
        ],
        'gift_builder' => [
            'key'    => 'builder',
            'fields' => ['image', 'link', 'text', 'schedule'],
            'label' => 'Gift box builder',
            'note'  => 'Shown on the build-a-box screen.',
            'multi' => false,
            'ratio' => '1600 × 500',
        ],
    ];

    /** A chooser: which slot do you want to work on? */
    /**
     * A slot's metadata, with every key the views read guaranteed present.
     *
     * A slot added without `ratio` took the whole Banners screen down with
     * "Undefined array key" — an admin page that 500s because ONE entry is
     * incomplete is a bad trade for a label nobody would have missed.
     *
     * @return array<string, mixed>
     */
    /**
     * Every slot, each with its defaults filled in.
     *
     * @return array<string, array<string, mixed>>
     */
    private function allSlotMeta(): array
    {
        $out = [];

        foreach (array_keys(self::SLOTS) as $position) {
            $out[$position] = $this->slotMeta($position);
        }

        return $out;
    }

    private function slotMeta(string $position): array
    {
        return array_merge([
            'key'    => $position,
            'fields' => ['image', 'link', 'text', 'schedule'],
            'label'  => ucfirst(str_replace('_', ' ', $position)),
            'note'   => '',
            'multi'  => false,
            'ratio'  => '',
        ], self::SLOTS[$position] ?? []);
    }

    public function index()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $counts = [];

        foreach (db_connect()->table('banners')
            ->select('position, COUNT(*) AS n', false)
            ->groupBy('position')->get()->getResultArray() as $row) {
            $counts[$row['position']] = (int) $row['n'];
        }

        return $this->adminPage('admin/banners/index', [
            'slots'  => $this->allSlotMeta(),
            'counts' => $counts,
        ], 'Banners');
    }

    /**
     * One slot, on its own page.
     *
     * Sections stacked on a single page were the wrong shape: a person managing
     * the gallery had to scroll past six other slots, and the "which slot?"
     * question was answered by a dropdown buried in the form rather than by
     * where they already were. A page per slot means the URL says what you are
     * editing, the browser remembers it, and the form has one less decision in
     * it.
     */
    public function slot(string $key)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $position = $this->positionFor($key);

        if ($position === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->adminPage('admin/banners/slot', [
            'slot'     => $position,
            'meta'     => $this->slotMeta($position),
            'banners'  => model(BannerModel::class)
                ->where('position', $position)
                ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll(),
            'slots'    => $this->allSlotMeta(),
        ], $this->slotMeta($position)['label']);
    }

    /** The stored position for a URL key, or null. */
    private function positionFor(string $key): ?string
    {
        foreach ($this->allSlotMeta() as $position => $meta) {
            if ($meta['key'] === $key) {
                return $position;
            }
        }

        return null;
    }

    public function create(string $key)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $position = $this->positionFor($key);

        if ($position === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->form(null, $position);
    }

    public function edit(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $banner = model(BannerModel::class)->find($id);

        if ($banner === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->form($banner, (string) $banner['position']);
    }

    public function store()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        return $this->save(null);
    }

    public function update(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        if (model(BannerModel::class)->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    /**
     * Add several images to one slot at once.
     *
     * A gallery of twelve photographs through a single-image form is twelve
     * round trips; this is the same operation done once. Each file becomes its
     * own banner so it can still be reordered, dated or removed individually.
     */
    public function bulk()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $slot = (string) $this->request->getPost('position');

        if (! isset(self::SLOTS[$slot])) {
            return redirect()->back()->with('error', 'That is not a banner slot.');
        }

        $files = $this->request->getFileMultiple('images');

        if ($files === null || $files === []) {
            return redirect()->back()->with('error', 'No images were chosen.');
        }

        $model  = model(BannerModel::class);
        $added  = 0;
        $errors = [];
        $notes  = [];

        $next = (int) ($model->selectMax('sort_order')->where('position', $slot)
            ->get()->getRowArray()['sort_order'] ?? 0);

        foreach ($files as $file) {
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $result = service('images')->store($file, 'banners');

            if (! $result['ok']) {
                $errors[] = $file->getClientName() . ': ' . $result['error'];

                continue;
            }

            if (($result['sharpness'] ?? null) !== null && $result['sharpness'] < 900) {
                $notes[] = '“' . $file->getClientName() . '” looks soft.';
            }

            $model->insert([
                'position'   => $slot,
                // No text: a bulk upload is artwork, so it renders bare until
                // someone opens it and writes a heading.
                'title'      => '',
                'image'      => $result['path'],
                'alt_text'   => null,
                'sort_order' => ++$next,
                'is_active'  => 1,
            ]);

            $added++;
        }

        service('audit')->log('created', 'content', 'banner', null, $added . ' image(s) added to ' . $slot);

        $message = $added . ' image' . ($added === 1 ? '' : 's') . ' added.'
            . ($notes === [] ? '' : ' ' . implode(' ', $notes))
            . ' Add alt text so they are described to screen readers.';

        return redirect()->to(site_url('admin/banners/' . $this->slotMeta($slot)['key']))
            ->with($errors === [] ? 'success' : 'error', $message . ($errors === [] ? '' : ' ' . implode(' ', $errors)));
    }

    public function delete(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $model  = model(BannerModel::class);
        $banner = $model->find($id);

        if ($banner === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! empty($banner['image'])) {
            service('images')->delete((string) $banner['image']);
        }

        $model->delete($id);
        service('audit')->log('deleted', 'content', 'banner', $id, (string) ($banner['title'] ?: $banner['position']));

        return redirect()->to(site_url('admin/banners/' . $this->slotMeta((string) $banner['position'])['key']))
            ->with('success', 'Banner removed.');
    }

    // -----------------------------------------------------------------

    private function form(?array $banner, string $slot): string
    {
        return $this->adminPage('admin/banners/form', [
            'banner' => $banner,
            'slot'   => $slot,
            'meta'   => $this->slotMeta($slot),
            'slots'  => $this->allSlotMeta(),
        ], $banner === null ? 'New banner' : 'Edit banner');
    }

    private function save(?int $id)
    {
        $model = model(BannerModel::class);
        $slot  = (string) $this->request->getPost('position');

        // Still validated: the field is hidden, and a hidden field is a posted
        // value like any other.
        if (! isset(self::SLOTS[$slot])) {
            return redirect()->back()->withInput()->with('error', 'That is not a banner slot.');
        }

        // Same-site only, for BOTH buttons. A banner link that accepted absolute
        // URLs would be a way to point a shop's own hero somewhere else.
        $link  = trim((string) $this->request->getPost('link_url'));
        $link2 = trim((string) $this->request->getPost('link_url_2'));

        foreach ([$link, $link2] as $candidate) {
            if ($candidate !== ''
                && (preg_match('#^[a-z]+://#i', $candidate) === 1 || str_starts_with($candidate, '//'))) {
                return redirect()->back()->withInput()->with(
                    'error',
                    'Links must be paths on this site, like /shop or /diwali-2026.'
                );
            }
        }

        $fields = $this->slotMeta($slot)['fields'];

        /*
         * Only the fields this slot actually renders are written, and the rest
         * are explicitly cleared. Without that, moving a banner from the hero to
         * the client row would leave a subtitle in the database that nothing
         * draws — invisible, and confusing the next time someone looks.
         */
        $payload = [
            'position'   => $slot,
            'title'      => trim((string) $this->request->getPost('title')),
            'subtitle'   => trim((string) $this->request->getPost('subtitle')) ?: null,
            'eyebrow'    => trim((string) $this->request->getPost('eyebrow')) ?: null,
            'alt_text'   => trim((string) $this->request->getPost('alt_text')) ?: null,
            'link_url'   => $link ?: null,
            'link_url_2' => $link2 ?: null,
            'cta_label'   => trim((string) $this->request->getPost('cta_label')) ?: null,
            'cta_label_2' => trim((string) $this->request->getPost('cta_label_2')) ?: null,
            'sort_order' => (int) $this->request->getPost('sort_order'),
            'starts_at'  => trim((string) $this->request->getPost('starts_at')) ?: null,
            'ends_at'    => trim((string) $this->request->getPost('ends_at')) ?: null,
            'is_active'  => $this->request->getPost('is_active') !== null ? 1 : 0,
        ];

        // 'text' = eyebrow + title + description. 'title' = the same without the
        // eyebrow, for slots that never draw one.
        if (! in_array('text', $fields, true) && ! in_array('title', $fields, true)) {
            $payload['subtitle'] = null;
        }

        if (! in_array('text', $fields, true)) {
            $payload['eyebrow'] = null;
        }

        if (! in_array('buttons', $fields, true)) {
            $payload['cta_label']   = null;
            $payload['cta_label_2'] = null;
            $payload['link_url_2']  = null;
        }

        if (! in_array('link', $fields, true)) {
            $payload['link_url'] = null;
        }

        if (! in_array('schedule', $fields, true)) {
            $payload['starts_at'] = null;
            $payload['ends_at']   = null;
        }

        $uploadError = null;

        foreach (['image', 'mobile_image'] as $field) {
            $file = $this->request->getFile($field);

            if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $result = service('images')->store($file, 'banners');

            if ($result['ok']) {
                $payload[$field] = $result['path'];
            } else {
                $uploadError = $result['error'];
            }
        }

        // See CLAUDE.md — {id} placeholders read from the payload.
        if ($id !== null) {
            $payload['id'] = $id;
        }

        $saved = $id === null ? $model->insert($payload) : $model->update($id, $payload);

        if ($id !== null) {
            unset($payload['id']);
        }

        if ($saved === false) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        service('audit')->log(
            $id === null ? 'created' : 'updated',
            'content',
            'banner',
            $id ?? (int) $model->getInsertID(),
            ($payload['title'] ?: 'Untitled') . ' — ' . $slot
        );

        return redirect()->to(site_url('admin/banners/' . $this->slotMeta($slot)['key']))
            ->with($uploadError !== null ? 'error' : 'success', $uploadError ?? 'Banner saved.');
    }
}
