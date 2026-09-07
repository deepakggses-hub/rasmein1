<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\PageModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * CMS pages.
 *
 * Content is HTML, and the storefront renders it unescaped — which is only safe
 * because PageModel sanitises on save through an allowlist. That callback is the
 * thing that makes this editor shippable; do not bypass the model to write
 * pages.content directly.
 */
class Pages extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        return $this->adminPage('admin/pages/index', [
            'pages' => model(PageModel::class)->orderBy('sort_order', 'ASC')->findAll(),
        ], 'Pages');
    }

    public function create()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        return $this->form(null, 'New page');
    }

    public function edit(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $page = model(PageModel::class)->find($id);

        if ($page === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->form($page, 'Edit ' . $page['title']);
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

        if (model(PageModel::class)->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    public function delete(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $model = model(PageModel::class);
        $page  = $model->find($id);

        if ($page === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->delete($id);
        service('audit')->log('deleted', 'content', 'page', $id, $page['title']);

        return redirect()->to(site_url('admin/pages'))->with('success', $page['title'] . ' removed.');
    }

    private function save(?int $id)
    {
        $model = model(PageModel::class);
        $title = trim((string) $this->request->getPost('title'));
        $slug  = trim((string) $this->request->getPost('slug')) ?: $title;
        $raw   = (string) $this->request->getPost('content');

        $payload = [
            'template' => $this->templateKey(),
            'data'     => $this->templateData(),
            'title'            => $title,
            'slug'             => strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $slug), '-')),
            'excerpt'          => trim((string) $this->request->getPost('excerpt')) ?: null,
            // Passed raw: PageModel's beforeInsert/beforeUpdate callback runs it
            // through the allowlist sanitiser. Sanitising here as well would
            // mean two places to keep in step.
            'content'          => $raw,
            'show_in_footer'   => $this->request->getPost('show_in_footer') !== null ? 1 : 0,
            'sort_order'       => (int) $this->request->getPost('sort_order'),
            'is_active'        => $this->request->getPost('is_active') !== null ? 1 : 0,
            'meta_title'       => trim((string) $this->request->getPost('meta_title')) ?: null,
            'meta_description' => trim((string) $this->request->getPost('meta_description')) ?: null,
        ];

        if ($id !== null) {
            $payload['id'] = $id;
        }

        $saved = $id === null ? $model->insert($payload) : $model->update($id, $payload);

        if ($saved === false) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $newId = $id ?? (int) $model->getInsertID();
        $stored = $model->find($newId);

        // If the sanitiser removed something, say so plainly rather than letting
        // the author wonder where their markup went.
        $stripped = $stored !== null && $raw !== '' && strlen((string) $stored['content']) < strlen($raw) * 0.9;

        service('audit')->log($id === null ? 'created' : 'updated', 'content', 'page', $newId, $payload['title']);

        return redirect()->to(site_url('admin/pages/' . $newId . '/edit'))
            ->with('success', 'Page saved.' . ($stripped ? ' Some markup was removed — only basic formatting is allowed.' : ''));
    }

    /**
     * The form, for both creating and editing.
     *
     * One place decides the template context, so a new page and an existing one
     * cannot disagree about which fields to show.
     *
     * @param array<string, mixed>|null $page
     */
    private function form(?array $page, string $heading): string
    {
        $config = config(\Config\PageTemplates::class);

        /*
         * A ?template= in the query wins, so the picker can reload the form
         * into a different layout before anything is saved. Otherwise it is
         * whatever the page already uses.
         */
        $templateKey = (string) ($this->request->getGet('template') ?: ($page['template'] ?? 'standard'));

        if (! isset($config->templates[$templateKey])) {
            $templateKey = 'standard';
        }

        $decoded  = ! empty($page['data']) ? json_decode((string) $page['data'], true) : [];
        $pageData = is_array($decoded) ? $decoded : [];

        /*
         * Defaults fill a template chosen for the first time — and only then.
         * Merging them into a page that HAS been edited would quietly resurrect
         * copy the shop deliberately cleared.
         */
        if ($pageData === [] || ($page['template'] ?? 'standard') !== $templateKey) {
            $pageData = array_replace_recursive($config->defaults($templateKey), $pageData);
        }

        return $this->adminPage('admin/pages/form', [
            'page'        => $page,
            'needsEditor' => true,
            'templates'   => $config->templates,
            'templateKey' => $templateKey,
            'template'    => $config->get($templateKey),
            'pageData'    => $pageData,
        ], $heading);
    }

    /** The chosen template, validated against the registry. */
    private function templateKey(): string
    {
        $key = (string) $this->request->getPost('template');

        return isset(config(\Config\PageTemplates::class)->templates[$key]) ? $key : 'standard';
    }

    /**
     * The template's own fields, as JSON.
     *
     * Only fields the CHOSEN template declares are kept — a posted key that no
     * template asks for is dropped rather than stored, so the column cannot
     * become a dumping ground for whatever was in the form. Empty repeated rows
     * are discarded, because three blank slots on screen should not become three
     * blank cards on the page.
     */
    private function templateData(): ?string
    {
        $key      = $this->templateKey();
        $template = config(\Config\PageTemplates::class)->get($key);
        $posted   = (array) $this->request->getPost('data');

        if ($template['sections'] === []) {
            return null;
        }

        $out = [];

        foreach ($template['sections'] as $sectionKey => $section) {
            foreach ($section['fields'] as $fieldKey => $field) {
                $value = $posted[$sectionKey][$fieldKey] ?? null;

                if ($field['type'] === 'occasions') {
                    /*
                     * A list of ids, filtered to ones that exist.
                     *
                     * Stored as ints so the view can compare without casting,
                     * and validated because a posted id that no longer names an
                     * occasion would render an empty tile forever.
                     */
                    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $value))));

                    $out[$sectionKey][$fieldKey] = $ids === [] ? [] : array_map('intval', array_column(
                        db_connect()->table('collections')->select('id')->whereIn('id', $ids)
                            ->get()->getResultArray(),
                        'id'
                    ));

                    continue;
                }

                if ($field['type'] === 'products') {
                    $out[$sectionKey][$fieldKey] = $this->validProductIds((array) $value);

                    continue;
                }

                if ($field['type'] === 'lines') {
                    // Trimmed and de-blanked on the way in, so a stray return
                    // never becomes an empty phrase on the page.
                    $out[$sectionKey][$fieldKey] = implode("\n", array_values(array_filter(array_map(
                        'trim',
                        preg_split('/\R/u', (string) $value) ?: []
                    ))));

                    continue;
                }

                if ($field['type'] === 'list') {
                    $rows = [];

                    foreach ((array) $value as $row) {
                        $clean = [];

                        foreach ($field['fields'] as $subKey => $subMeta) {
                            // A picker inside a row stays an ARRAY; everything
                            // else is a trimmed string.
                            $clean[$subKey] = ($subMeta['type'] ?? '') === 'products'
                                ? $this->validProductIds((array) ($row[$subKey] ?? []))
                                : trim((string) ($row[$subKey] ?? ''));
                        }

                        /*
                         * A row where every box is empty is not a row.
                         *
                         * implode() would fatal on the array a product picker
                         * leaves behind, so each value is tested for its own
                         * kind of emptiness.
                         */
                        $filled = array_filter(
                            $clean,
                            static fn ($v): bool => is_array($v) ? $v !== [] : $v !== ''
                        );

                        if ($filled !== []) {
                            $rows[] = $clean;
                        }
                    }

                    $out[$sectionKey][$fieldKey] = $rows;

                    continue;
                }

                if ($field['type'] === 'image') {
                    $file = $this->request->getFile('data_image_' . $sectionKey . '_' . $fieldKey);

                    if ($file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                        $result = service('images')->store($file, 'content');

                        // A failed upload keeps the old image rather than
                        // clearing it — losing a picture because a file was too
                        // large is not what anyone meant.
                        $out[$sectionKey][$fieldKey] = $result['ok']
                            ? $result['path']
                            : trim((string) $value);

                        continue;
                    }
                }

                $out[$sectionKey][$fieldKey] = trim((string) $value);
            }
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Posted product ids, filtered to ones that exist.
     *
     * A page holding an id that no longer names a product renders a gap with
     * nothing explaining it, and the shop has no way to tell which row is at
     * fault.
     *
     * @return list<int>
     */
    private function validProductIds(array $posted): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $posted))));

        if ($ids === []) {
            return [];
        }

        return array_map('intval', array_column(
            db_connect()->table('products')->select('id')
                ->whereIn('id', $ids)->where('deleted_at', null)->get()->getResultArray(),
            'id'
        ));
    }
}
