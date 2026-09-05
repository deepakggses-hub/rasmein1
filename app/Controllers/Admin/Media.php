<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Alt text for every image on the site, in one place.
 *
 * WHY A CENTRAL SCREEN
 *
 * Alt text is the thing everybody intends to fill in and nobody does, because
 * it lives one field at a time inside eight different forms. Gathering it here
 * — with the missing ones counted and listed first — turns it from a chore
 * spread across the panel into a single sitting.
 *
 * Each row still links to the screen that owns the image, so this is a way to
 * SEE and FIX alt text, not a second place that owns it.
 */
class Media extends AdminController
{
    /**
     * Where images live. Each entry describes one table so the screen, the
     * save, and the counts all read from the same definition — three copies of
     * this list would drift.
     *
     * @var array<string, array<string, mixed>>
     */
    private const SOURCES = [
        'product' => [
            'label'   => 'Product photographs',
            'table'   => 'product_images',
            'image'   => 'path',
            'title'   => null,
            'join'    => ['products', 'products.id = product_images.product_id', 'products.name'],
            'editUrl' => 'admin/products/{owner}/edit',
            'soft'    => false,
        ],
        'category' => [
            'label'   => 'Categories',
            'table'   => 'categories',
            'image'   => 'image',
            'title'   => 'name',
            'join'    => null,
            'editUrl' => 'admin/categories/{id}/edit',
            'soft'    => true,
        ],
        'collection' => [
            'label'   => 'Collections and occasions',
            'table'   => 'collections',
            'image'   => 'image',
            'title'   => 'name',
            'join'    => null,
            'editUrl' => 'admin/occasions/{id}/edit',
            'soft'    => true,
        ],
        'banner' => [
            'label'   => 'Banners',
            'table'   => 'banners',
            'image'   => 'image',
            'title'   => 'title',
            'join'    => null,
            'editUrl' => 'admin/banners/{id}/edit',
            'soft'    => false,
        ],
        'testimonial' => [
            'label'   => 'Testimonials',
            'table'   => 'testimonials',
            'image'   => 'image',
            'title'   => 'author',
            'join'    => null,
            'editUrl' => 'admin/homepage/testimonials/{id}',
            'soft'    => true,
        ],
    ];

    public function index()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $only    = (string) $this->request->getGet('source');
        $missing = $this->request->getGet('missing') !== null;

        $groups  = [];
        $counts  = ['total' => 0, 'missing' => 0];

        foreach (self::SOURCES as $key => $source) {
            $rows = $this->rowsFor($key, $source);

            foreach ($rows as $row) {
                $counts['total']++;

                if (trim((string) $row['alt_text']) === '') {
                    $counts['missing']++;
                }
            }

            if ($missing) {
                $rows = array_values(array_filter(
                    $rows,
                    static fn (array $r): bool => trim((string) $r['alt_text']) === ''
                ));
            }

            if ($only !== '' && $only !== $key) {
                continue;
            }

            if ($rows !== []) {
                $groups[$key] = ['label' => $source['label'], 'rows' => $rows];
            }
        }

        return $this->adminPage('admin/media/index', [
            'groups'  => $groups,
            'counts'  => $counts,
            'sources' => array_map(static fn (array $s): string => $s['label'], self::SOURCES),
            'only'    => $only,
            'missing' => $missing,
        ], 'Image alt text');
    }

    public function save()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $posted  = (array) $this->request->getPost('alt');
        $db      = db_connect();
        $changed = 0;

        foreach ($posted as $composite => $value) {
            // "source:id" — both halves are validated below, so a crafted key
            // cannot reach a table that is not on the list.
            if (! str_contains((string) $composite, ':')) {
                continue;
            }

            [$source, $id] = explode(':', (string) $composite, 2);

            if (! isset(self::SOURCES[$source]) || (int) $id <= 0) {
                continue;
            }

            $db->table(self::SOURCES[$source]['table'])
                ->where('id', (int) $id)
                ->update(['alt_text' => mb_substr(trim((string) $value), 0, 191) ?: null]);

            $changed += $db->affectedRows();
        }

        service('audit')->log('alt_text_updated', 'content', 'image', null, $changed . ' image(s)');

        return redirect()->back()->with(
            'success',
            $changed === 0 ? 'Nothing changed.' : $changed . ' image(s) updated.'
        );
    }

    // -----------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function rowsFor(string $key, array $source): array
    {
        $db      = db_connect();
        $table   = $source['table'];
        $builder = $db->table($table);

        $select = $table . '.id, ' . $table . '.' . $source['image'] . ' AS image, '
            . $table . '.alt_text';

        if ($source['join'] !== null) {
            [$joinTable, $on, $titleColumn] = $source['join'];
            $builder->join($joinTable, $on, 'left');
            $select .= ', ' . $titleColumn . ' AS title, ' . $joinTable . '.id AS owner';
        } else {
            $select .= ', ' . $table . '.' . $source['title'] . ' AS title, ' . $table . '.id AS owner';
        }

        $builder->select($select, false);

        if ($source['soft']) {
            $builder->where($table . '.deleted_at', null);
        }

        // An image field that is empty has nothing to describe.
        $builder->where($table . '.' . $source['image'] . ' IS NOT NULL', null, false)
            ->where($table . '.' . $source['image'] . ' !=', '');

        $out = [];

        foreach ($builder->orderBy($table . '.id', 'ASC')->get(400)->getResultArray() as $row) {
            $out[] = $row + [
                'source'  => $key,
                'editUrl' => str_replace(
                    ['{id}', '{owner}'],
                    [(string) $row['id'], (string) ($row['owner'] ?? $row['id'])],
                    (string) $source['editUrl']
                ),
            ];
        }

        return $out;
    }
}
