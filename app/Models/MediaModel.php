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
        $existing = $this->where('path', $path)->first();

        if ($existing !== null) {
            return $existing;
        }

        $full = FCPATH . ltrim($path, '/');
        $size = is_file($full) ? (int) filesize($full) : 0;
        $dims = is_file($full) ? @getimagesize($full) : false;

        $id = $this->insert([
            'path'       => $path,
            'filename'   => mb_substr($filename, 0, 191),
            'mime'       => $dims !== false ? ($dims['mime'] ?? null) : null,
            'bytes'      => $size,
            'width'      => $dims !== false ? (int) $dims[0] : null,
            'height'     => $dims !== false ? (int) $dims[1] : null,
            'collection' => $collection,
        ], true);

        return $this->find($id) ?? [];
    }
}
