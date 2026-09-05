<?php

declare(strict_types=1);

namespace App\Services;

use Config\Rasmein;
use GdImage;

/**
 * Builds the responsive size ladder for an uploaded image.
 *
 * WHAT THIS FIXES
 *
 * A single stored file cannot be both sharp on a large monitor and cheap on a
 * phone. Every upload therefore produces a set of widths and a WebP of each, and
 * the markup hands the browser a `srcset` so it picks the smallest file that
 * still covers the slot at the device's pixel density.
 *
 * WHAT IT CANNOT FIX
 *
 * Detail that was never captured. Sharpening raises edge contrast, which reads
 * as crisper, but no filter recovers information a soft photograph does not
 * contain. `estimateSharpness()` exists so the admin can WARN at upload time
 * rather than quietly pretending.
 *
 * Variants live beside the original with a width suffix, so nothing new is
 * stored in the database: `uploads/products/2026/07/abc.jpg` gains
 * `abc-768.jpg` and `abc-768.webp` next to it. The column keeps the same
 * relative path it always had.
 */
class ImageVariantService
{
    /**
     * Generate every variant for a stored image.
     *
     * @param string $relativePath e.g. uploads/products/2026/07/abc.jpg
     *
     * @return array{widths: list<int>, webp: bool}
     */
    public function build(string $relativePath): array
    {
        $config = config(Rasmein::class);
        $full   = FCPATH . ltrim($relativePath, '/');

        if (! is_file($full)) {
            return ['widths' => [], 'webp' => false];
        }

        $size = @getimagesize($full);

        if ($size === false) {
            return ['widths' => [], 'webp' => false];
        }

        [$srcWidth, $srcHeight, $type] = $size;

        $source = $this->open($full, $type);

        if ($source === null) {
            return ['widths' => [], 'webp' => false];
        }

        $made = [];
        $webp = false;

        try {
            foreach ($config->imageWidths as $width) {
                // Never upscale. A 600px original blown up to 1920 is a bigger
                // file that looks worse than letting the browser stretch it.
                if ($width > $srcWidth) {
                    continue;
                }

                $height = max(1, (int) round($srcHeight * ($width / $srcWidth)));
                $canvas = $this->resample($source, $srcWidth, $srcHeight, $width, $height, $type);

                if ($canvas === null) {
                    continue;
                }

                $this->sharpen($canvas, $config->sharpenAmount);

                $stem = $this->stem($full);

                if ($this->write($canvas, $stem . '-' . $width . '.' . $this->ext($type), $type, $config)) {
                    $made[] = $width;
                }

                // WebP of the same width: same visual quality, far fewer bytes.
                if (function_exists('imagewebp') && @imagewebp($canvas, $stem . '-' . $width . '.webp', $config->webpQuality)) {
                    $webp = true;
                }

                imagedestroy($canvas);
            }

            // The full-size original also gets a WebP, for slots wider than the
            // largest ladder step.
            if (function_exists('imagewebp')) {
                $webp = @imagewebp($source, $this->stem($full) . '.webp', $config->webpQuality) || $webp;
            }
        } finally {
            imagedestroy($source);
        }

        return ['widths' => $made, 'webp' => $webp];
    }

    /**
     * Remove every variant of a stored image.
     *
     * Called when the original is deleted, or the files accumulate forever.
     */
    public function purge(string $relativePath): int
    {
        $full = FCPATH . ltrim($relativePath, '/');
        $stem = $this->stem($full);

        // Guard: only ever inside the upload tree, and never with traversal.
        if (str_contains($relativePath, '..') || ! str_starts_with($relativePath, 'uploads/')) {
            log_message('warning', 'Refused to purge variants outside uploads: {p}', ['p' => $relativePath]);

            return 0;
        }

        $removed = 0;

        foreach ((array) glob($stem . '-*.{jpg,jpeg,png,webp}', GLOB_BRACE) as $file) {
            if (is_file($file) && @unlink($file)) {
                $removed++;
            }
        }

        if (is_file($stem . '.webp') && @unlink($stem . '.webp')) {
            $removed++;
        }

        return $removed;
    }

    /**
     * A rough sharpness score for an uploaded image.
     *
     * The variance of a Laplacian-filtered copy: flat, out-of-focus images have
     * little high-frequency content and score low. It is a heuristic, not a
     * verdict — a deliberately soft product shot on a white background will also
     * score low — so it is used to WARN, never to reject.
     *
     * @return float Roughly 0 (featureless) to 100+ (crisp)
     */
    public function estimateSharpness(string $fullPath): float
    {
        $size = @getimagesize($fullPath);

        if ($size === false) {
            return 100.0;
        }

        $image = $this->open($fullPath, $size[2]);

        if ($image === null) {
            return 100.0;
        }

        try {
            // Work on a small copy: sharpness is scale-relative, and this keeps
            // the check cheap on a 6000px photograph.
            $sample = imagescale($image, 320);

            if ($sample === false) {
                return 100.0;
            }

            $width  = imagesx($sample);
            $height = imagesy($sample);

            // Laplacian kernel: responds to edges, ignores flat areas.
            $edges = imagecreatetruecolor($width, $height);
            imagecopy($edges, $sample, 0, 0, 0, 0, $width, $height);
            imageconvolution($edges, [[0, 1, 0], [1, -4, 1], [0, 1, 0]], 1, 128);

            $sum = 0.0;
            $sumSq = 0.0;
            $n = 0;

            // Every fourth pixel: plenty for a variance estimate.
            for ($y = 0; $y < $height; $y += 2) {
                for ($x = 0; $x < $width; $x += 2) {
                    $rgb = imagecolorat($edges, $x, $y);
                    $v = (($rgb >> 16 & 0xFF) + ($rgb >> 8 & 0xFF) + ($rgb & 0xFF)) / 3;
                    $sum += $v;
                    $sumSq += $v * $v;
                    $n++;
                }
            }

            imagedestroy($sample);
            imagedestroy($edges);

            if ($n === 0) {
                return 100.0;
            }

            $mean = $sum / $n;

            return round(max(0.0, ($sumSq / $n) - ($mean * $mean)), 1);
        } finally {
            imagedestroy($image);
        }
    }

    // =================================================================

    private function open(string $path, int $type): ?GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => false,
        };

        return $image === false ? null : $image;
    }

    private function resample(GdImage $source, int $sw, int $sh, int $dw, int $dh, int $type): ?GdImage
    {
        $canvas = imagecreatetruecolor($dw, $dh);

        if ($canvas === false) {
            return null;
        }

        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
        }

        // Resampled, not resized: imagecopyresized drops pixels and produces the
        // jagged, aliased look people describe as "cheap".
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dw, $dh, $sw, $sh);

        return $canvas;
    }

    /**
     * A mild unsharp mask.
     *
     * Downscaling averages neighbouring pixels, which is exactly what blurring
     * is — so the result is always slightly softer than the original looked.
     * This restores the edge contrast that averaging removed. Kept gentle: too
     * much produces halos, which look worse than the softness being corrected.
     */
    private function sharpen(GdImage $image, float $amount): void
    {
        if ($amount <= 0 || ! function_exists('imageconvolution')) {
            return;
        }

        $amount = min(1.5, $amount);
        $centre = 1.0 + (4.0 * $amount);
        $side   = -$amount;

        $kernel = [
            [0.0,   $side, 0.0],
            [$side, $centre, $side],
            [0.0,   $side, 0.0],
        ];

        // Divisor equals the kernel sum, so overall brightness is unchanged —
        // without that, sharpening also lightens or darkens the picture.
        $divisor = array_sum(array_map('array_sum', $kernel));

        @imageconvolution($image, $kernel, $divisor > 0 ? $divisor : 1, 0);
    }

    private function write(GdImage $image, string $target, int $type, Rasmein $config): bool
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagejpeg($image, $target, $config->jpegQuality),
            IMAGETYPE_PNG  => @imagepng($image, $target, 6),
            IMAGETYPE_WEBP => @imagewebp($image, $target, $config->webpQuality),
            default        => false,
        };
    }

    private function ext(int $type): string
    {
        return match ($type) {
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_WEBP => 'webp',
            default        => 'jpg',
        };
    }

    /** The path without its extension. */
    private function stem(string $full): string
    {
        $dot = strrpos($full, '.');

        return $dot === false ? $full : substr($full, 0, $dot);
    }
}
