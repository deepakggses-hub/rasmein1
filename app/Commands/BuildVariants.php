<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Rasmein;

/**
 * Build the responsive size ladder for images already on disk.
 *
 * WHY THIS EXISTS
 *
 * Every image that arrives through ImageUploadService gets its ladder built on
 * the way in. Images that arrive any other way — copied into public/uploads by
 * hand, restored from a backup, or placed there by an import script — do not.
 *
 * That failure is SILENT: `rs_picture()` finds no variants, falls back to the
 * single stored file, and the page renders correctly while every phone
 * downloads the full-size photograph. Nothing errors and nothing says so.
 *
 * Safe to re-run. A file that already has its full ladder is skipped unless
 * --force is passed, so this can be pointed at the whole uploads tree after a
 * deploy without rebuilding thousands of images.
 */
class BuildVariants extends BaseCommand
{
    protected $group       = 'Rasmein';
    protected $name        = 'rasmein:build-variants';
    protected $description = 'Build missing responsive image variants for files already in public/uploads.';
    protected $usage       = 'rasmein:build-variants [dir] [--force] [--dry-run]';
    protected $arguments   = [
        'dir' => 'Path under public/ to scan. Default: uploads',
    ];
    protected $options = [
        '--force'   => 'Rebuild even where every variant already exists.',
        '--dry-run' => 'Report what would be built and change nothing.',
    ];

    public function run(array $params): void
    {
        $dir = trim($params[0] ?? 'uploads', '/');

        // Keep the scan inside public/. A traversal here would let a mistyped
        // argument walk the filesystem looking for images to rewrite.
        if (str_contains($dir, '..') || $dir === '') {
            CLI::error('The directory must be a simple path under public/.');

            return;
        }

        $root = FCPATH . $dir;

        if (! is_dir($root)) {
            CLI::error('No such directory: public/' . $dir);

            return;
        }

        $force  = array_key_exists('force', $params) || in_array('--force', $params, true);
        $dryRun = array_key_exists('dry-run', $params) || in_array('--dry-run', $params, true);
        $widths = config(Rasmein::class)->imageWidths ?? [320, 480, 768, 1024, 1440, 1920];

        $service = service('imageVariants');

        $built   = 0;
        $skipped = 0;
        $failed  = [];

        foreach ($this->originals($root) as $full) {
            /*
             * Normalise BOTH sides before stripping the root. On Windows
             * FCPATH carries backslashes, so replacing separators first means
             * the root no longer matches the path and nothing is stripped —
             * which yields "uploads/C:/xampp/.../uploads/x.jpg", a path that
             * does not exist, and every file is reported as a decode failure.
             */
            $slashed  = str_replace('\\', '/', $full);
            $rootPath = rtrim(str_replace('\\', '/', $root), '/') . '/';
            $relative = $dir . '/' . ltrim(substr($slashed, strlen($rootPath)), '/');

            if (! $force && $this->isComplete($full, $widths)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                CLI::write('  would build  ' . $relative);
                $built++;

                continue;
            }

            $result = $service->build($relative);

            if ($result['widths'] === []) {
                $failed[] = $relative;

                continue;
            }

            $built++;
        }

        CLI::write('');
        CLI::write('  built   : ' . $built . ($dryRun ? ' (dry run, nothing written)' : ''), 'green');
        CLI::write('  skipped : ' . $skipped . ' (already complete)');

        if ($failed !== []) {
            CLI::write('  failed  : ' . count($failed), 'red');

            foreach ($failed as $f) {
                CLI::write('    - ' . $f, 'red');
            }

            CLI::write('  A failure here is usually a file GD cannot decode — a CMYK JPEG,');
            CLI::write('  a truncated download, or something that is not an image at all.');
        }
    }

    /**
     * Every original image under the root — variants and WebP twins excluded.
     *
     * A generated file matches `-<width>.<ext>` where the width is a real
     * ladder step, so a photograph genuinely named "banner-2024.jpg" is not
     * mistaken for a variant. Same reasoning as MediaModel::isVariant(): one
     * rule, not a second copy that drifts when the ladder changes.
     *
     * @return iterable<string>
     */
    private function originals(string $root): iterable
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        $widths = implode('|', config(Rasmein::class)->imageWidths ?? [320, 480, 768, 1024, 1440, 1920]);

        foreach ($it as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $name = $file->getFilename();

            if (preg_match('/\.(jpe?g|png)$/i', $name) !== 1) {
                continue; // .webp originals are rare and their twin is generated
            }

            if (preg_match('/-(' . $widths . ')\.[a-z]+$/i', $name) === 1) {
                continue; // a generated variant
            }

            yield $file->getPathname();
        }
    }

    /**
     * Does this original already have every variant it should?
     *
     * A width wider than the source is deliberately never generated — the
     * service refuses to upscale — so it must not count as missing here, or
     * every small image would be rebuilt on every run.
     */
    private function isComplete(string $full, array $widths): bool
    {
        $size = @getimagesize($full);

        if ($size === false) {
            return false;
        }

        $srcWidth  = $size[0];
        $stem      = preg_replace('/\.[^.]+$/', '', $full);
        $extension = pathinfo($full, PATHINFO_EXTENSION);

        if (! is_file($stem . '.webp')) {
            return false;
        }

        foreach ($widths as $w) {
            if ($w >= $srcWidth) {
                continue; // never upscaled, so never expected
            }

            if (! is_file($stem . '-' . $w . '.' . $extension) || ! is_file($stem . '-' . $w . '.webp')) {
                return false;
            }
        }

        return true;
    }
}
