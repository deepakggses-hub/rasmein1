<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\BrochureModel;
use App\Services\BrochureService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\HTTP\Files\UploadedFile;
use Throwable;

/**
 * Smoke-test the brochure module, php.ini included.
 *
 * WHY php.ini IS THE FIRST CHECK
 *
 * PHP discards an oversized upload before the application sees a byte, so a
 * server whose `upload_max_filesize` is below our own ceiling refuses files
 * the form says are fine. Reported from the field: a 5.4 MB PDF answered with
 * "Choose a PDF to upload", because an upload php.ini refuses arrives as an
 * ERROR CODE rather than as a file — and the old code treated "no file" and
 * "a file that failed" as the same thing.
 *
 * CLI-only, like every other diag. Not web-reachable.
 */
class DiagBrochures extends BaseCommand
{
    protected $group       = 'Rasmein';
    protected $name        = 'rasmein:diag-brochures';
    protected $description = 'Check brochure uploads, storage and the PHP limits that govern them.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params): void
    {
        CLI::write('Brochures', 'yellow');
        CLI::newLine();

        $this->limits();
        $this->storage();
        $this->errorMessages();
        $this->rows();

        CLI::newLine();
        CLI::write(
            $this->pass . ' passed, ' . $this->fail . ' failed',
            $this->fail === 0 ? 'green' : 'red',
        );
    }

    // -------------------------------------------------------- php.ini

    private function limits(): void
    {
        $service = service('brochures');
        $upload  = (string) ini_get('upload_max_filesize');
        $post    = (string) ini_get('post_max_size');

        CLI::write('  php.ini', 'white');
        CLI::write('    upload_max_filesize : ' . $upload);
        CLI::write('    post_max_size       : ' . $post);
        CLI::write('    our own ceiling     : ' . (int) (BrochureService::MAX_BYTES / 1048576) . ' MB');
        CLI::write('    effective limit     : ' . round($service->effectiveMaxBytes() / 1048576, 1) . ' MB');

        $this->check(
            'a brochure-sized PDF (10 MB) can actually be uploaded',
            $service->effectiveMaxBytes() >= 10 * 1048576,
            'php.ini caps uploads at ' . round($service->effectiveMaxBytes() / 1048576, 1)
                . ' MB. Raise upload_max_filesize AND post_max_size — post_max_size must be'
                . ' the larger of the two, or the whole request is discarded and even the'
                . ' CSRF token never arrives.',
        );

        /*
         * post_max_size below upload_max_filesize is the nastier misconfig:
         * the body is thrown away whole, so $_POST is empty too and the
         * failure surfaces as a CSRF rejection rather than an upload error.
         */
        $this->check(
            'post_max_size is at least upload_max_filesize',
            $this->bytes($post) === 0 || $this->bytes($post) >= $this->bytes($upload),
            'post_max_size (' . $post . ') is below upload_max_filesize (' . $upload
                . '). A large upload then loses the entire request body.',
        );
    }

    // -------------------------------------------------------- storage

    private function storage(): void
    {
        CLI::newLine();
        CLI::write('  storage', 'white');

        $dir = WRITEPATH . BrochureService::DIR;

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $this->check('writable/' . BrochureService::DIR . ' exists', is_dir($dir), 'Could not create it.');
        $this->check('…and is writable', is_dir($dir) && is_writable($dir), 'Check permissions.');

        // The whole security property: the gate is only real if the files are
        // not also sitting under the document root.
        $this->check(
            'brochures are NOT inside public/',
            ! is_dir(FCPATH . BrochureService::DIR),
            'A copy under public/ is fetchable without the lead form, which makes the form decoration.',
        );
    }

    // -------------------------------- what the admin is told on failure

    private function errorMessages(): void
    {
        CLI::newLine();
        CLI::write('  what an upload failure says', 'white');

        $cases = [
            UPLOAD_ERR_INI_SIZE   => 'php.ini refused it',
            UPLOAD_ERR_FORM_SIZE  => 'the form limit refused it',
            UPLOAD_ERR_PARTIAL    => 'it arrived half-finished',
            UPLOAD_ERR_NO_TMP_DIR => 'there is no temp directory',
            UPLOAD_ERR_CANT_WRITE => 'it could not be written',
        ];

        foreach ($cases as $code => $label) {
            try {
                $file = new UploadedFile('', 'catalogue.pdf', 'application/pdf', 0, $code);

                // Exactly the test Admin\Brochures::save() makes.
                $picked  = $file->getError() !== UPLOAD_ERR_NO_FILE;
                $message = $file->getErrorString();

                $this->check(
                    $label . ' is reported as a failure, not as "no file"',
                    $picked && ! $file->isValid() && $message !== '',
                    'The admin would be told to choose a file they had already chosen.',
                );

                CLI::write('      -> ' . $message, 'dark_gray');
            } catch (Throwable $e) {
                $this->check($label . ' produces a message', false, $e->getMessage());
            }
        }

        $none = new UploadedFile('', '', '', 0, UPLOAD_ERR_NO_FILE);

        $this->check(
            'choosing nothing still says "choose a file"',
            $none->getError() === UPLOAD_ERR_NO_FILE,
            'UPLOAD_ERR_NO_FILE is the ONLY code that means nothing was picked.',
        );
    }

    // ------------------------------------------------------- the rows

    private function rows(): void
    {
        CLI::newLine();
        CLI::write('  stored brochures', 'white');

        try {
            $model = model(BrochureModel::class);
            $rows  = $model->findAll();
        } catch (Throwable $e) {
            CLI::write('    skipped — no database (' . $e->getMessage() . ')', 'dark_gray');

            return;
        }

        CLI::write('    ' . count($rows) . ' brochure(s)');

        $service = service('brochures');
        $missing = [];

        foreach ($rows as $row) {
            if ($service->fullPath($row) === null) {
                $missing[] = $row['title'] . ' (' . $row['path'] . ')';
            }
        }

        $this->check(
            'every brochure row has its file on disk',
            $missing === [],
            'Missing: ' . implode(', ', $missing),
        );

        // A shop with brochures and no default shows nothing on any page that
        // has not been assigned one — which is most of them.
        if ($rows !== []) {
            $this->check(
                'one brochure is the default',
                $model->default() !== null,
                'Unassigned pages will show no download button at all.',
            );
        }
    }

    // =================================================================

    private function check(string $label, bool $ok, string $hint = ''): void
    {
        if ($ok) {
            $this->pass++;
            CLI::write('    [ok]   ' . $label, 'green');

            return;
        }

        $this->fail++;
        CLI::write('    [FAIL] ' . $label, 'red');

        if ($hint !== '') {
            CLI::write('           ' . $hint, 'dark_gray');
        }
    }

    private function bytes(string $value): int
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
}
