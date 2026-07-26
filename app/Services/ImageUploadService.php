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
     * @return array{ok: bool, path: string|null, error: string|null}
     */
    public function store(UploadedFile $file, string $destination = 'products'): array
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

        // ---- Generated filename. Nothing from the client survives. ----
        $extension = self::ACCEPTED[$type];
        $name      = date('Y/m/') . bin2hex(random_bytes(16)) . '.' . $extension;
        $fullPath  = $directory . '/' . $name;
        $subfolder = dirname($fullPath);

        if (! is_dir($subfolder) && ! @mkdir($subfolder, 0o755, true) && ! is_dir($subfolder)) {
            return $fail('The upload directory could not be created.');
        }

        try {
            $written = $this->reencode($temporary, $fullPath, $type, $width, $height, $config->maxImageWidth);
        } catch (Throwable $e) {
            log_message('error', 'Image processing failed: {msg}', ['msg' => $e->getMessage()]);

            return $fail('That image could not be processed. Try re-saving it and uploading again.');
        }

        if (! $written) {
            return $fail('That image could not be processed.');
        }

        // Readable by the web server, never executable.
        @chmod($fullPath, 0o644);

        return [
            'ok'    => true,
            'path'  => $config->uploadPaths[$destination] . '/' . $name,
            'error' => null,
        ];
    }

    /**
     * Decode and write again. This is the security step, not an optimisation:
     * a polyglot file that is both valid PHP and a valid image does not
     * survive being turned back into pixels and re-encoded.
     */
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
