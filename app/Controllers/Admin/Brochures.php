<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\BrochureLeadModel;
use App\Models\BrochureModel;
use App\Models\CategoryModel;
use App\Models\CollectionModel;
use App\Models\PageModel;
use App\Services\BrochureService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Brochures, what they are attached to, and the leads they produced.
 *
 * ONE SCREEN FOR THE ATTACHMENT
 *
 * The assignment is edited here rather than as a field on every category,
 * occasion and page form. Someone deciding which brochure goes where is doing
 * one job, and doing it across three separate CRUD screens means holding the
 * whole map in their head. It also keeps the polymorphic write in one place.
 */
class Brochures extends AdminController
{
    private const PERM = 'brochures.manage';

    public function index(): string|RedirectResponse
    {
        if (($deny = $this->deny(self::PERM)) !== null) {
            return $deny;
        }

        $model = model(BrochureModel::class);
        $rows  = $model->orderBy('is_default', 'DESC')->orderBy('title', 'ASC')->findAll();

        // One query for every lead count, rather than one per brochure.
        $counts = [];

        foreach ($model->db->table('brochure_leads')
            ->select('brochure_id, COUNT(*) AS n')
            ->groupBy('brochure_id')->get()->getResultArray() as $row) {
            $counts[(int) $row['brochure_id']] = (int) $row['n'];
        }

        return $this->adminPage('admin/brochures/index', [
            'brochures'   => $rows,
            'leadCounts'  => $counts,
            'assignments' => service('brochures')->assignments(),
            'targets'     => $this->targets(),
            /*
             * The EFFECTIVE limit, not our own constant. php.ini can be the
             * lower of the two, and a form promising 25 MB on a 2 MB server
             * sends people to support with a file the page told them was fine.
             */
            'maxMb'       => (int) floor(service('brochures')->effectiveMaxBytes() / 1048576),
            'phpCaps'     => service('brochures')->phpLimitsAreTighter(),
            'iniUpload'   => (string) ini_get('upload_max_filesize'),
            'iniPost'     => (string) ini_get('post_max_size'),
        ], 'Brochures');
    }

    /**
     * Create or replace a brochure.
     *
     * A failed file upload on an EDIT keeps the existing file rather than
     * clearing it — the same rule the product and page forms follow, because
     * losing the artwork while fixing a typo is the worst outcome here.
     */
    public function save(?int $id = null): RedirectResponse
    {
        if (($deny = $this->deny(self::PERM)) !== null) {
            return $deny;
        }

        $model    = model(BrochureModel::class);
        $existing = $id !== null ? $model->find($id) : null;

        if ($id !== null && $existing === null) {
            return redirect()->to(site_url('admin/brochures'))->with('error', 'No such brochure.');
        }

        $payload = [
            'title'       => trim((string) $this->request->getPost('title')),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'is_active'   => $this->request->getPost('is_active') !== null ? 1 : 0,
        ];

        $file = $this->request->getFile('brochure');

        /*
         * "No file" and "a file that PHP refused" are DIFFERENT, and saying
         * "Choose a PDF to upload" for both sent someone hunting for a file
         * they had already chosen. UPLOAD_ERR_NO_FILE is the only code that
         * means nothing was picked; every other code is a real failure with a
         * real cause, and the person needs to be told which.
         */
        $picked = $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE;

        if ($picked && ! $file->isValid()) {
            log_message('error', 'Brochure upload refused by PHP: code {c} ({m}) for "{n}"', [
                'c' => $file->getError(),
                'm' => $file->getErrorString(),
                'n' => $file->getClientName(),
            ]);

            return redirect()->back()->withInput()
                ->with('error', 'That file did not upload: ' . $file->getErrorString());
        }

        if ($picked) {
            $error  = null;
            $stored = service('brochures')->store($file, $error);

            if ($stored === null) {
                return redirect()->back()->withInput()->with('error', $error ?? 'That file was refused.');
            }

            $payload += $stored;
        } elseif ($existing === null) {
            return redirect()->back()->withInput()->with('error', 'Choose a PDF to upload.');
        }

        if ($existing === null) {
            // Not in $allowedFields as a default, so a first brochure would
            // otherwise land with nothing to fall back to.
            $payload['download_count'] = 0;

            if (! $model->insert($payload)) {
                return redirect()->back()->withInput()->with('errors', $model->errors());
            }

            $newId = (int) $model->getInsertID();

            // The very first brochure becomes the default: a shop with one
            // brochure and no default has a module that does nothing.
            if ($model->where('deleted_at', null)->countAllResults() === 1) {
                $model->makeDefault($newId);
            }

            service('audit')->log('create', 'brochures', 'brochure', $newId, $payload['title']);

            return redirect()->to(site_url('admin/brochures'))->with('success', 'Brochure added.');
        }

        // Model::update() does not inject the primary key, so {id} placeholders
        // would compare the row against nothing.
        $payload['id'] = $id;

        if (! $model->update($id, $payload)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        // The old file only goes once the new row is safely written.
        if (isset($payload['path']) && $existing['path'] !== $payload['path']) {
            service('brochures')->deleteFile($existing);
        }

        service('audit')->logChange('brochures', 'brochure', (int) $id, $existing, $payload, $payload['title']);

        return redirect()->to(site_url('admin/brochures'))->with('success', 'Brochure updated.');
    }

    public function makeDefault(int $id): RedirectResponse
    {
        if (($deny = $this->deny(self::PERM)) !== null) {
            return $deny;
        }

        $model = model(BrochureModel::class);
        $row   = $model->find($id);

        if ($row === null) {
            return redirect()->to(site_url('admin/brochures'))->with('error', 'No such brochure.');
        }

        if ((int) $row['is_active'] !== 1) {
            return redirect()->to(site_url('admin/brochures'))
                ->with('error', 'Switch it on before making it the default — an inactive default is no default.');
        }

        $model->makeDefault($id);
        service('audit')->log('update', 'brochures', 'brochure', $id, 'Default brochure: ' . $row['title']);

        return redirect()->to(site_url('admin/brochures'))
            ->with('success', $row['title'] . ' is now the default.');
    }

    /**
     * Soft-delete a brochure and remove its file.
     *
     * The LEADS survive: `brochure_id` is SET NULL and each lead carries a
     * title snapshot. Somebody gave us their details and that record is worth
     * more than the file it was about.
     */
    public function delete(int $id): RedirectResponse
    {
        if (($deny = $this->deny(self::PERM)) !== null) {
            return $deny;
        }

        $model = model(BrochureModel::class);
        $row   = $model->find($id);

        if ($row === null) {
            return redirect()->to(site_url('admin/brochures'))->with('error', 'No such brochure.');
        }

        if ((int) $row['is_default'] === 1) {
            return redirect()->to(site_url('admin/brochures'))
                ->with('error', 'Make another brochure the default first — every unassigned page points at this one.');
        }

        $model->delete($id);
        service('brochures')->deleteFile($row);
        service('audit')->log('delete', 'brochures', 'brochure', $id, $row['title']);

        return redirect()->to(site_url('admin/brochures'))->with('success', 'Brochure removed.');
    }

    /** Point pages at brochures. The whole map is posted as one form. */
    public function assign(): RedirectResponse
    {
        if (($deny = $this->deny(self::PERM)) !== null) {
            return $deny;
        }

        $posted  = (array) $this->request->getPost('target');
        $service = service('brochures');
        $changed = 0;

        foreach ($posted as $key => $brochureId) {
            // "type:id" — both halves are checked, so a crafted key cannot
            // reach another table. Same rule as the alt-text screen.
            [$type, $targetId] = array_pad(explode(':', (string) $key, 2), 2, '');

            if (! in_array($type, BrochureService::TARGETS, true) || ! ctype_digit((string) $targetId)) {
                continue;
            }

            $service->assign($type, (int) $targetId, (int) $brochureId ?: null);
            $changed++;
        }

        service('audit')->log('update', 'brochures', 'assignment', null, $changed . ' page(s) updated');

        return redirect()->to(site_url('admin/brochures'))->with('success', 'Brochure assignments saved.');
    }

    // ------------------------------------------------------------- leads

    public function leads(): string|RedirectResponse
    {
        if (($deny = $this->deny(self::PERM)) !== null) {
            return $deny;
        }

        $filters = [
            'q'        => trim((string) $this->request->getGet('q')),
            'brochure' => (int) $this->request->getGet('brochure'),
        ];

        $model   = model(BrochureLeadModel::class);
        $perPage = 30;
        $page    = max(1, (int) $this->request->getGet('page'));
        $total   = $model->countFiltered($filters);

        return $this->adminPage('admin/brochures/leads', [
            'leads'     => $model->page($perPage, ($page - 1) * $perPage, $filters),
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'pages'     => (int) ceil($total / $perPage),
            'filters'   => $filters,
            'brochures' => model(BrochureModel::class)->orderBy('title', 'ASC')->findAll(),
        ], 'Brochure leads');
    }

    /**
     * Export the filtered leads.
     *
     * Through CsvExporter, never fputcsv directly: a cell starting `=` `+` `-`
     * or `@` executes as a formula in Excel and Sheets, and a lead's "notes"
     * field is free text a stranger typed.
     */
    public function exportLeads(): ?RedirectResponse
    {
        if (($deny = $this->deny(self::PERM)) !== null) {
            return $deny;
        }

        $filters = [
            'q'        => trim((string) $this->request->getGet('q')),
            'brochure' => (int) $this->request->getGet('brochure'),
        ];

        $rows = model(BrochureLeadModel::class)->page(5000, 0, $filters);

        $out = [];

        foreach ($rows as $row) {
            $out[] = [
                $row['created_at'],
                $row['brochure_title'],
                $row['name'],
                $row['email'],
                $row['phone'],
                $row['customer_id'] !== null ? 'Account' : 'Guest',
                $row['source_type'] ?? '',
                $row['notes'] ?? '',
            ];
        }

        service('audit')->log('export', 'brochures', 'lead', null, count($out) . ' lead(s)');

        // stream() writes and exits; every cell goes through neutralise()
        // inside it, which is what keeps a "notes" field out of Excel's
        // formula parser.
        service('csv')->stream(
            'rasmein-brochure-leads-' . date('Y-m-d') . '.csv',
            ['Date', 'Brochure', 'Name', 'Email', 'Phone', 'Who', 'From', 'Notes'],
            $out,
        );

        exit;
    }

    // =================================================================

    /**
     * Every page a brochure can attach to, grouped for the form.
     *
     * @return array<string, array<int, array{id: int, label: string}>>
     */
    private function targets(): array
    {
        $categories = model(CategoryModel::class)
            ->where('is_active', 1)->orderBy('path', 'ASC')->findAll();

        $collections = model(CollectionModel::class)
            ->where('is_active', 1)->orderBy('type', 'ASC')->orderBy('name', 'ASC')->findAll();

        $pages = model(PageModel::class)->orderBy('title', 'ASC')->findAll();

        $out = ['category' => [], 'collection' => [], 'page' => []];

        foreach ($categories as $row) {
            // CategoryModel returns ENTITIES; CollectionModel returns arrays.
            // They are not interchangeable and assuming otherwise has already
            // produced a TypeError on the live occasion page once.
            $out['category'][] = ['id' => (int) $row->id, 'label' => (string) $row->path];
        }

        foreach ($collections as $row) {
            $out['collection'][] = [
                'id'    => (int) $row['id'],
                'label' => $row['name'] . ($row['type'] === 'occasion' ? ' (occasion)' : ' (collection)'),
            ];
        }

        foreach ($pages as $row) {
            $out['page'][] = ['id' => (int) $row['id'], 'label' => (string) $row['title']];
        }

        return $out;
    }
}
