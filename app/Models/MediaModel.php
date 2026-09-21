<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class MediaModel extends Model
{
    protected $table         = 'media';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'path', 'filename', 'alt_text', 'mime', 'bytes', 'width', 'height', 'collection',
    ];

    protected $validationRules = [
        'id'       => 'permit_empty|is_natural_no_zero',
        'path'     => 'required|max_length[255]',
        'filename' => 'required|max_length[191]',
    ];

    /**
     * Browse the library.
     *
     * Searches filename AND alt text, because the two answer different
     * questions: a filename says what the photographer called it, alt text says
     * what it shows. Someone hunting for "the gold bowl" will match one or the
     * other, rarely both.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function browse(string $term = '', string $collection = '', int $page = 1, int $perPage = 40): array
    {
        $builder = $this->orderBy('created_at', 'DESC')->orderBy('id', 'DESC');

        if ($collection !== '') {
            $builder->where('collection', $collection);
        }

        $term = trim($term);

        if ($term !== '') {
            $builder->groupStart()
                ->like('filename', $term)
                ->orLike('alt_text', $term)
                ->groupEnd();
        }

        // countAllResults(false) keeps the conditions for the fetch that
        // follows — without the false it resets them and the page returns the
        // whole table.
        $total = $builder->countAllResults(false);

        return [
            'items' => $builder->findAll($perPage, max(0, ($page - 1) * $perPage)),
            'total' => $total,
        ];
    }

    /**
     * Record an upload, or return the row that already has this path.
     *
     * The uploader writes a unique filename, so a clash means the SAME file —
     * returning the existing row keeps the library from growing a duplicate the
     * shop then has to choose between.
     *
     * @return array<string, mixed>
     */
    public function remember(string $path, string $filename, string $collection = 'content'): array
    {
        // A variant is never an entry in its own right, whoever asks.
        if (self::isVariant($path)) {
            return [];
        }

        $existing = $this->where('path', $path)->first();

        if ($existing !== null) {
            return $existing;
        }

        $full = FCPATH . ltrim($path, '/');
        $size = is_file($full) ? (int) filesize($full) : 0;
        $dims = is_file($full) ? @getimagesize($full) : false;

        /*
         * The file's own modified time, not "now".
         *
         * A backfill stamps every row with the same instant, so "newest first"
         * would order by nothing at all — the library would look shuffled to
         * anyone who knew what they uploaded last.
         */
        $when = is_file($full) ? date('Y-m-d H:i:s', (int) filemtime($full)) : null;

        $id = $this->insert([
            'path'       => $path,
            'filename'   => mb_substr($filename, 0, 191),
            'mime'       => $dims !== false ? ($dims['mime'] ?? null) : null,
            'bytes'      => $size,
            'width'      => $dims !== false ? (int) $dims[0] : null,
            'height'     => $dims !== false ? (int) $dims[1] : null,
            'collection' => $collection,
        ], true);

        /*
         * Stamp the real date AFTER the insert.
         *
         * `$useTimestamps` sets created_at to "now" and overwrites anything
         * passed in, so the correction has to be a second write. Straight to
         * the builder, because going through the model would re-apply the
         * timestamp it is here to replace.
         */
        if ($id !== false && $when !== null) {
            $this->db->table($this->table)->where('id', $id)
                ->set(['created_at' => $when, 'updated_at' => $when])->update();
        }

        return $this->find($id) ?? [];
    }

    /**
     * Is this path a generated size variant rather than an original?
     *
     * ImageVariantService writes `name-400.jpg`, `name-1200.webp` and a
     * same-width `name.webp` beside every upload. Listing those shows one
     * photograph six times and lets someone pick a 400px copy for a hero band.
     *
     * ONE rule, used by the backfill, the uploader and delete alike — three
     * copies of this test would disagree the first time the ladder changed.
     */
    public static function isVariant(string $path): bool
    {
        // A trailing -<digits> before the extension is a width variant.
        if (preg_match('/-\d{2,5}\.[a-z0-9]+$/i', $path) === 1) {
            return true;
        }

        /*
         * A .webp sitting beside an original of another type is the full-size
         * WebP twin, not an upload in its own right. A .webp that was itself
         * uploaded has no such sibling, so it stays.
         */
        if (preg_match('/\.webp$/i', $path) === 1) {
            $stem = substr($path, 0, -5);

            foreach (['jpg', 'jpeg', 'png'] as $ext) {
                if (is_file(FCPATH . $stem . '.' . $ext)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Every file belonging to one original: the original and its variants.
     *
     * @return list<string> paths relative to public/
     */
    public static function family(string $path): array
    {
        $dot  = strrpos($path, '.');
        $stem = $dot === false ? $path : substr($path, 0, $dot);
        $out  = [$path];

        foreach (glob(FCPATH . $stem . '-*') ?: [] as $file) {
            $out[] = ltrim(str_replace(FCPATH, '', $file), '/');
        }

        // The full-size WebP twin sits at the stem, not under a width.
        if (is_file(FCPATH . $stem . '.webp') && ! str_ends_with($path, '.webp')) {
            $out[] = $stem . '.webp';
        }

        return array_values(array_unique($out));
    }
}
