<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BrochureLeadModel;
use App\Models\BrochureModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use Throwable;

/**
 * Brochures: which one a page offers, and who is allowed to download it.
 *
 * WHERE THE FILES LIVE
 *
 * Under WRITEPATH, never public/. A brochure sitting behind a lead form and
 * ALSO fetchable at its own URL is not gated at all — the form becomes
 * decoration the moment one person shares the direct link. Everything is
 * streamed by the controller, which is also the only place the download is
 * counted.
 *
 * WHAT IS AND IS NOT VALIDATED
 *
 * The upload is checked by extension AND by sniffed MIME AND by reading the
 * first bytes for `%PDF-`. A PDF cannot be re-encoded the way an image can
 * (ImageUploadService destroys polyglots by re-encoding; there is no
 * equivalent here), so the checks are the whole of the protection and the
 * stored name is generated rather than taken from the client.
 */
class BrochureService
{
    /** Relative to WRITEPATH. */
    public const DIR = 'uploads/brochures';

    /** 25 MB. A catalogue is large; a 200 MB one is a mistake, not a brochure. */
    public const MAX_BYTES = 25 * 1024 * 1024;

    /**
     * What can ACTUALLY be uploaded here.
     *
     * PHP discards an oversized upload before the application sees a byte of
     * it, so our own ceiling is only the limit when php.ini allows at least
     * that much. A form advertising "max 25 MB" on a server configured for 2
     * MB is a lie that reads as a bug in the application — which is exactly
     * how it was reported: a 5.4 MB PDF answered with "Choose a PDF to
     * upload", because an upload refused by php.ini arrives as an error code
     * rather than as a file.
     */
    public function effectiveMaxBytes(): int
    {
        $limits = [self::MAX_BYTES];

        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $bytes = $this->iniBytes((string) ini_get($key));

            // 0 or unset means "no limit" for post_max_size, so it is ignored.
            if ($bytes > 0) {
                $limits[] = $bytes;
            }
        }

        return min($limits);
    }

    /** True when php.ini is the binding constraint, not our own cap. */
    public function phpLimitsAreTighter(): bool
    {
        return $this->effectiveMaxBytes() < self::MAX_BYTES;
    }

    /** "2M" / "8M" / "512K" / "1G" as bytes. */
    private function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $unit   = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        return match ($unit) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => (int) $value,
        };
    }

    /** The three things a brochure can be attached to. */
    public const TARGETS = ['category', 'collection', 'page'];

    /**
     * The brochure a page should offer, or the default, or nothing.
     *
     * A target row pointing at a brochure that has been deleted or switched
     * off falls through to the default rather than erroring — the database
     * cannot enforce a polymorphic reference, so this is where that is made
     * safe.
     *
     * @return array<string, mixed>|null
     */
    public function forPage(?string $targetType, ?int $targetId): ?array
    {
        $model = model(BrochureModel::class);

        if ($targetType !== null && $targetId !== null && in_array($targetType, self::TARGETS, true)) {
            $row = $model->db->table('brochure_targets t')
                ->select('t.brochure_id')
                ->where('t.target_type', $targetType)
                ->where('t.target_id', $targetId)
                ->get()->getRowArray();

            if ($row !== null) {
                $brochure = $model->where('is_active', 1)->find((int) $row['brochure_id']);

                if ($brochure !== null) {
                    return $brochure;
                }
            }
        }

        return $model->default();
    }

    /**
     * Every page's assignment, keyed "type:id" — one query for a whole screen.
     *
     * @return array<string, int>
     */
    public function assignments(): array
    {
        $rows = model(BrochureModel::class)->db->table('brochure_targets')
            ->select('target_type, target_id, brochure_id')
            ->get()->getResultArray();

        $map = [];

        foreach ($rows as $row) {
            $map[$row['target_type'] . ':' . $row['target_id']] = (int) $row['brochure_id'];
        }

        return $map;
    }

    /**
     * Point a page at a brochure, or clear it when $brochureId is null.
     *
     * REPLACE, not insert: the unique key on (target_type, target_id) is what
     * makes "one brochure per page" true, and an insert would simply fail the
     * second time somebody changed their mind.
     */
    public function assign(string $targetType, int $targetId, ?int $brochureId): void
    {
        if (! in_array($targetType, self::TARGETS, true) || $targetId < 1) {
            return;
        }

        $db = model(BrochureModel::class)->db;

        $db->table('brochure_targets')
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->delete();

        if ($brochureId === null || $brochureId < 1) {
            return;
        }

        // A posted id is checked against the table, or a hand-edited form
        // could point a page at a row that does not exist.
        if ($db->table('brochures')->where('id', $brochureId)->where('deleted_at', null)->countAllResults() === 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $db->table('brochure_targets')->insert([
            'brochure_id' => $brochureId,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    /**
     * Store an uploaded PDF and return what the model needs.
     *
     * @return array{path: string, filename: string, mime: string, bytes: int}|null
     *                                                          null on refusal
     */
    public function store(UploadedFile $file, ?string &$error = null): ?array
    {
        if (! $file->isValid()) {
            $error = 'That upload did not arrive properly. Try again.';

            return null;
        }

        if ($file->getSize() > self::MAX_BYTES) {
            $error = 'That file is ' . round($file->getSize() / 1048576, 1)
                . ' MB. The limit is ' . (self::MAX_BYTES / 1048576) . ' MB.';

            return null;
        }

        if (strtolower($file->getExtension()) !== 'pdf') {
            $error = 'Brochures must be PDF files.';

            return null;
        }

        $mime = (string) $file->getMimeType();

        if (! in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
            $error = 'That does not look like a PDF.';

            return null;
        }

        /*
         * Read the signature as well. The extension is the client's word and
         * the sniffed type is a guess from the same bytes — neither alone is
         * worth much, and a PDF cannot be re-encoded the way an image can, so
         * there is no third line of defence after this.
         */
        $handle = @fopen($file->getTempName(), 'rb');
        $head   = $handle !== false ? (string) fread($handle, 5) : '';

        if ($handle !== false) {
            fclose($handle);
        }

        if ($head !== '%PDF-') {
            $error = 'That file is not a PDF, whatever it is named.';

            return null;
        }

        $dir = WRITEPATH . self::DIR . '/' . date('Y/m');

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $error = 'The brochure folder could not be created.';

            return null;
        }

        // Generated, never the client's name: nothing from the request reaches
        // the path. The original is kept in a column for the download name.
        $stored = bin2hex(random_bytes(16)) . '.pdf';

        if (! $file->move($dir, $stored, true)) {
            $error = 'The file could not be saved.';

            return null;
        }

        $relative = self::DIR . '/' . date('Y/m') . '/' . $stored;

        return [
            'path'     => $relative,
            'filename' => $this->safeDownloadName($file->getClientName()),
            'mime'     => 'application/pdf',
            'bytes'    => (int) filesize(WRITEPATH . $relative),
        ];
    }

    /**
     * A filename safe to put in a Content-Disposition header.
     *
     * Quotes, semicolons and newlines in that header are a response-splitting
     * and filename-spoofing surface, so the name is rebuilt from a narrow set
     * rather than escaped.
     */
    public function safeDownloadName(string $clientName): string
    {
        $stem = pathinfo(basename($clientName), PATHINFO_FILENAME);
        $stem = preg_replace('/[^A-Za-z0-9 ._-]+/', '', $stem) ?? '';
        $stem = trim(preg_replace('/\s+/', ' ', $stem) ?? '');

        if ($stem === '') {
            $stem = 'brochure';
        }

        return mb_substr($stem, 0, 120) . '.pdf';
    }

    /** Absolute path of a stored brochure, or null when the file has gone. */
    public function fullPath(array $brochure): ?string
    {
        $path = (string) ($brochure['path'] ?? '');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $full = WRITEPATH . $path;

        return is_file($full) ? $full : null;
    }

    /** Remove the file as well as the row's claim on it. */
    public function deleteFile(array $brochure): void
    {
        $full = $this->fullPath($brochure);

        if ($full !== null) {
            @unlink($full);
        }
    }

    /**
     * Record who downloaded what.
     *
     * Failure is caught and logged: the lead matters, but refusing someone
     * their brochure because a row would not insert is the worse outcome, and
     * they have already given us their details by this point.
     */
    public function recordLead(array $brochure, array $details): void
    {
        try {
            model(BrochureLeadModel::class)->insert([
                'brochure_id'    => (int) $brochure['id'],
                'brochure_title' => (string) $brochure['title'],
                'customer_id'    => $details['customer_id'] ?? null,
                'name'           => $details['name'],
                'email'          => $details['email'],
                'phone'          => $details['phone'],
                'notes'          => $details['notes'] ?? null,
                'source_type'    => $details['source_type'] ?? null,
                'source_id'      => $details['source_id'] ?? null,
                'source_url'     => $details['source_url'] ?? null,
                'ip_address'     => service('request')->getIPAddress(),
                // rs_user_agent(): a CLIRequest has no getUserAgent(), and
                // shared code that assumes it crashes from spark and cron.
                'user_agent'     => rs_user_agent(),
            ], false);

            model(BrochureModel::class)->countDownload((int) $brochure['id']);

            service('notify')->toStaff(
                'brochure_downloaded',
                $details['name'] . ' downloaded ' . $brochure['title'],
                'enquiries.view',
                [
                    'link'        => site_url('admin/brochures/leads'),
                    'entity_type' => 'brochure',
                    'entity_id'   => (int) $brochure['id'],
                ],
            );
        } catch (Throwable $e) {
            log_message('error', 'BrochureService: lead not recorded: {m}', ['m' => $e->getMessage()]);
        }
    }
}
