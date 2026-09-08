<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\MediaModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Put images already on disk into the library.
 *
 * Without this the picker opens empty on an existing shop, and everything ever
 * uploaded is invisible to it — which is exactly the problem the library was
 * built to solve.
 *
 * Alt text is copied from whichever record uses the file, because that is where
 * someone already wrote it.
 */
class BackfillMedia extends BaseCommand
{
    protected $group       = 'Rasmein';
    protected $name        = 'rasmein:backfill-media';
    protected $description = 'Add existing uploads to the media library.';

    public function run(array $params): void
    {
        $model = model(MediaModel::class);
        $db    = db_connect();

        // Alt text already written elsewhere, keyed by path.
        $alt = [];

        foreach ([
            ['product_images', 'path', 'alt_text'],
            ['categories', 'image', 'alt_text'],
            ['collections', 'image', 'alt_text'],
            ['banners', 'image', 'alt_text'],
        ] as [$table, $pathCol, $altCol]) {
            if (! $db->tableExists($table)) {
                continue;
            }

            foreach ($db->table($table)->select($pathCol . ', ' . $altCol)
                ->where($pathCol . ' IS NOT NULL')->where($pathCol . ' !=', '')
                ->get()->getResultArray() as $row) {
                if (! empty($row[$altCol])) {
                    $alt[(string) $row[$pathCol]] = (string) $row[$altCol];
                }
            }
        }

        $root  = FCPATH . 'uploads';
        $added = 0;
        $seen  = 0;

        if (! is_dir($root)) {
            CLI::write('  No uploads directory.', 'yellow');

            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());

            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true)) {
                continue;
            }

            $path = 'uploads/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            /*
             * Skip the generated size variants.
             *
             * ImageVariantService writes thumb/card/hero copies beside the
             * original; listing them would show the same picture six times and
             * let someone pick a 200px thumbnail for a hero band.
             */
            if (preg_match('/-(thumb|card|hero|content|products|banners|\d{3,4}w)\.[a-z]+$/i', $path) === 1) {
                continue;
            }

            $seen++;

            if ($model->where('path', $path)->countAllResults() > 0) {
                continue;
            }

            $row = $model->remember($path, basename($path), $this->collectionFor($path));

            if (isset($alt[$path]) && $row !== []) {
                $model->update((int) $row['id'], ['id' => (int) $row['id'], 'alt_text' => $alt[$path]]);
            }

            $added++;
        }

        CLI::write('  Media library: ' . $added . ' added, ' . $seen . ' image(s) on disk.', 'green');
    }

    /** The folder an image sits in is the best guess at what it is for. */
    private function collectionFor(string $path): string
    {
        $parts = explode('/', $path);

        return $parts[1] ?? 'content';
    }
}
