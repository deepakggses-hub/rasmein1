<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Rasmein;
use Throwable;

/**
 * Handling an uploaded image safely.
 *
 * The rules, and why each one is here:
 *
 *  1. The type is decided by READING the file, never by its extension or the
 *     Content-Type the browser claims. Both are attacker-controlled.
 *  2. The image is RE-ENCODED through GD rather than moved. A file that only
 *     pretends to be a PNG will not survive being decoded and written again,
 *     and re-encoding also strips EXIF — which routinely carries the GPS
 *     coordinates of wherever the photo was taken.
 *  3. The filename is generated. A client filename can carry traversal
 *     sequences, null bytes, or a second extension like "cat.php.jpg".
 *  4. Dimensions and byte size are capped, so one upload cannot exhaust memory
 *     or fill the disk.
 *  5. The destination directory is fixed by a key, not by anything posted.
 */
class ImageUploadService
{
    /** GD signature => canonical extension. Anything else is rejected. */
    private const ACCEPTED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    /**
     * @param string $destination One of Rasmein::$uploadPaths — a key, so a
     *                            posted value can never steer the path.
     *
     * @param int|null $maxWidth Override the default width cap. A logo needs a
     *                            far smaller one than a product photograph.
     *
     * @return array{ok: bool, path: string|null, error: string|null, width?: int, height?: int}
     */
    public function store(UploadedFile $file, string $destination = 'products', ?int $maxWidth = null): array
    {
        $config = config(Rasmein::class);

        $fail = static fn (string $message): array => ['ok' => false, 'path' => null, 'error' => $message];

        if (! isset($config->uploadPaths[$destination])) {
            return $fail('Unknown upload destination.');
        }

        if (! $file->isValid()) {
            return $fail($this->describeUploadError($file));
        }

        if ($file->hasMoved()) {
            return $fail('That file has already been handled.');
        }

        if ($file->getSize() > $config->maxImageBytes) {
            return $fail(
                'Images must be under ' . round($config->maxImageBytes / 1048576, 1) . ' MB. '
                . 'That one is ' . round($file->getSize() / 1048576, 1) . ' MB.'
            );
        }

        $temporary = $file->getTempName();

        // ---- Decide the type by reading the file, not by trusting labels ----
        $info = @getimagesize($temporary);

        if ($info === false || ! isset($info[2])) {
            return $fail('That does not appear to be an image.');
        }

        [$width, $height, $type] = $info;

        if (! isset(self::ACCEPTED[$type])) {
            return $fail('Images must be JPEG, PNG or WebP.');
        }

        if ($width < 1 || $height < 1) {
            return $fail('That image has no dimensions.');
        }

        // A modest pixel ceiling: a "decompression bomb" can be a tiny file
        // that expands to gigabytes in memory.
        if ($width * $height > 50_000_000) {
            return $fail('That image is too large to process.');
        }

        if (! extension_loaded('gd')) {
            return $fail('Image processing is unavailable on this server (ext-gd is not installed).');
        }

        $directory = FCPATH . $config->uploadPaths[$destination];

        if (! is_dir($directory) && ! @mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            return $fail('The upload directory could not be created.');
        }

        if (! is_writable($directory)) {
            return $fail('The upload directory is not writable.');
        }

        /*
         * ---- Filename ----
         *
         * The uploaded name is KEPT, because a findable, readable filename is
         * worth having — but only its slugified stem survives, and the
         * EXTENSION always comes from the detected type. That last part is the
         * whole security property: "cat.php.jpg" is stored as "cat-php.jpg"
         * because the type was read from the file's own bytes, not its name.
         */
        $extension = self::ACCEPTED[$type];
        $stem      = $this->safeStem($file->getClientName(), $config);

        $subfolder = $directory . '/' . date('Y/m');

        if (! is_dir($subfolder) && ! @mkdir($subfolder, 0o755, true) && ! is_dir($subfolder)) {
            return $fail('The upload directory could not be created.');
        }

        $stem     = $this->uniqueStem($subfolder, $stem, $extension, $config);
        $name     = date('Y/m/') . $stem . '.' . $extension;
        $fullPath = $directory . '/' . $name;

        try {
            $cap     = $maxWidth !== null ? max(16, min(4000, $maxWidth)) : $config->maxImageWidth;
            $written = $this->reencode($temporary, $fullPath, $type, $width, $height, $cap);
        } catch (Throwable $e) {
            log_message('error', 'Image processing failed: {msg}', ['msg' => $e->getMessage()]);

            return $fail('That image could not be processed. Try re-saving it and uploading again.');
        }

        if (! $written) {
            return $fail('That image could not be processed.');
        }

        // Readable by the web server, never executable.
        @chmod($fullPath, 0o644);

        // Report what actually landed, so a caller can show the true size
        // rather than the size that was chosen.
        $stored = @getimagesize($fullPath);
        $path   = $config->uploadPaths[$destination] . '/' . $name;

        /*
         * Build the responsive ladder now, at upload time, rather than on first
         * request. A visitor should never wait for a resize, and doing it here
         * means a failure is visible to the person uploading instead of to a
         * customer.
         */
        $variants = ['widths' => [], 'webp' => false];
        $sharpness = null;

        try {
            $variants  = service('imageVariants')->build($path);
            $sharpness = service('imageVariants')->estimateSharpness($fullPath);
        } catch (\Throwable $e) {
            // A missing variant degrades to the original being served, which is
            // correct but heavier — worth logging, not worth failing the upload.
            log_message('error', 'Image variants failed for {p}: {m}', ['p' => $path, 'm' => $e->getMessage()]);
        }

        return [
            'ok'        => true,
            'path'      => $path,
            'error'     => null,
            'width'     => $stored !== false ? (int) $stored[0] : 0,
            'height'    => $stored !== false ? (int) $stored[1] : 0,
            'widths'    => $variants['widths'],
            'webp'      => $variants['webp'],
            // A heuristic, surfaced so the admin can warn. Never used to reject:
            // a deliberately soft product shot scores low too.
            'sharpness' => $sharpness,
        ];
    }

    /**
     * Decode and write again. This is the security step, not an optimisation:
     * a polyglot file that is both valid PHP and a valid image does not
     * survive being turned back into pixels and re-encoded.
     */
    /**
     * Turn a client filename into a safe stem.
     *
     * Everything dangerous about a filename lives here: path separators, "..",
     * null bytes, a second extension, control characters, right-to-left
     * override marks, Windows reserved names, and lengths that overflow a
     * filesystem. Rather than try to spot each, the name is reduced to
     * [a-z0-9-] and rebuilt — anything not on that list simply cannot survive.
     */
    private function safeStem(string $clientName, Rasmein $config): string
    {
        // basename() first: it discards any directory part, including "../".
        $stem = basename(trim($clientName));

        // Drop the client's extension entirely. The real one is added later
        // from the detected type, so "cat.php.jpg" loses ".jpg" here and the
        // remaining ".php" becomes an ordinary "-php" inside the stem.
        $dot = strrpos($stem, '.');

        if ($dot !== false && $dot > 0) {
            $stem = substr($stem, 0, $dot);
        }

        // Transliterate accents so "Café Diya" becomes "cafe-diya" rather than
        // losing both words.
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $stem);

            if ($converted !== false) {
                $stem = $converted;
            }
        }

        $stem = strtolower($stem);
        $stem = (string) preg_replace('/[^a-z0-9]+/', '-', $stem);
        $stem = trim($stem, '-');

        // A filesystem-safe length that still leaves room for "-1920.webp".
        $stem = substr($stem, 0, 80);
        $stem = trim($stem, '-');

        /*
         * A stem ending in "-<width>" would collide with the responsive ladder:
         * uploading "photo-320.jpg" and later "photo.jpg" would have the second
         * one's 320px variant overwrite the first file. Rare, silent, and very
         * confusing — so those names get a suffix.
         */
        if (preg_match('/-(\d{2,4})$/', $stem, $m) === 1
            && in_array((int) $m[1], $config->imageWidths, true)) {
            $stem .= '-img';
        }

        // Windows reserves these as device names, and a file called con.jpg
        // cannot be created there. Costs three lines to sidestep.
        if (in_array($stem, [
            'con', 'prn', 'aux', 'nul',
            'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9',
            'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9',
        ], true)) {
            $stem .= '-image';
        }

        // Nothing usable survived (a name that was entirely punctuation, or
        // entirely non-Latin script). Fall back rather than write ".jpg".
        return $stem !== '' ? $stem : 'image-' . bin2hex(random_bytes(4));
    }

    /**
     * The first free variation of a stem in this folder.
     *
     * Appends -2, -3 and so on, as a person would expect. The check covers the
     * responsive variants too: reserving "photo.jpg" must also reserve
     * "photo-320.jpg", or a later upload's ladder would overwrite an existing
     * file.
     */
    private function uniqueStem(string $folder, string $stem, string $extension, Rasmein $config): string
    {
        $taken = function (string $candidate) use ($folder, $extension, $config): bool {
            if (file_exists($folder . '/' . $candidate . '.' . $extension)
                || file_exists($folder . '/' . $candidate . '.webp')) {
                return true;
            }

            foreach ($config->imageWidths as $width) {
                if (file_exists($folder . '/' . $candidate . '-' . $width . '.' . $extension)) {
                    return true;
                }
            }

            return false;
        };

        if (! $taken($stem)) {
            return $stem;
        }

        // Bounded: a folder with a thousand same-named files is a problem of a
        // different kind, and an unbounded loop here would hang the request.
        for ($n = 2; $n <= 999; $n++) {
            if (! $taken($stem . '-' . $n)) {
                return $stem . '-' . $n;
            }
        }

        return $stem . '-' . bin2hex(random_bytes(4));
    }

    private function reencode(
        string $source,
        string $target,
        int $type,
        int $width,
        int $height,
        int $maxWidth
    ): bool {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG  => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            default        => false,
        };

        if ($image === false) {
            return false;
        }

        try {
            if ($width > $maxWidth) {
                $newHeight = (int) round($height * ($maxWidth / $width));
                $resized   = imagecreatetruecolor($maxWidth, $newHeight);

                // Keep transparency for the formats that have it.
                if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                }

                imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }

            return match ($type) {
                IMAGETYPE_JPEG => imagejpeg($image, $target, 85),
                IMAGETYPE_PNG  => imagepng($image, $target, 6),
                IMAGETYPE_WEBP => imagewebp($image, $target, 85),
                default        => false,
            };
        } finally {
            if ($image instanceof \GdImage) {
                imagedestroy($image);
            }
        }
    }

    /** Delete a stored image. Path must be one we generated. */
    public function delete(?string $path): void
    {
        if ($path === null || trim($path) === '') {
            return;
        }

        $config = config(Rasmein::class);

        // Only ever inside a known upload directory — never an arbitrary path.
        $allowed = false;

        foreach ($config->uploadPaths as $base) {
            if (str_starts_with($path, $base . '/')) {
                $allowed = true;
                break;
            }
        }

        if ($allowed && ! str_contains($path, '..')) {
            // Otherwise every replaced image leaves a litter of variants behind.
            try {
                service('imageVariants')->purge($path);
            } catch (\Throwable $e) {
                log_message('error', 'Variant purge failed for {p}: {m}', ['p' => $path, 'm' => $e->getMessage()]);
            }
        }

        if (! $allowed || str_contains($path, '..')) {
            log_message('warning', 'Refused to delete an image outside the upload tree: {p}', ['p' => $path]);

            return;
        }

        $full = FCPATH . $path;

        if (is_file($full)) {
            @unlink($full);
        }
    }

    private function describeUploadError(UploadedFile $file): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is larger than the server accepts.',
            UPLOAD_ERR_PARTIAL                        => 'The upload was interrupted. Try again.',
            UPLOAD_ERR_NO_FILE                        => 'No file was chosen.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not save the file.',
            default                                   => 'That file could not be uploaded.',
        };
    }
}
