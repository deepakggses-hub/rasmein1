<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** What customers said. Shown on the homepage. */
class TestimonialModel extends Model
{
    protected $table         = 'testimonials';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;

    protected $allowedFields = ['quote', 'author', 'role', 'rating', 'image', 'is_active', 'sort_order'];

    protected $validationRules = [
        // Required for {id} route placeholders — see CLAUDE.md.
        'id'         => 'permit_empty|is_natural_no_zero',
        'quote'      => 'required|max_length[600]',
        'author'     => 'required|max_length[120]',
        'role'       => 'permit_empty|max_length[160]',
        'rating'     => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[5]',
        'sort_order' => 'permit_empty|integer',
    ];

    /** @return array<int, array<string, mixed>> */
    public function live(int $limit = 3): array
    {
        return $this->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'DESC')
            ->findAll($limit);
    }

    /** The headline figure beside the section title. @return array{average: float, count: int} */
    public function summary(): array
    {
        $row = $this->select('AVG(rating) AS avg_rating, COUNT(*) AS n', false)
            ->where('is_active', 1)
            ->where('deleted_at', null)
            ->get()->getRowArray();

        return [
            'average' => round((float) ($row['avg_rating'] ?? 0), 1),
            'count'   => (int) ($row['n'] ?? 0),
        ];
    }
}
