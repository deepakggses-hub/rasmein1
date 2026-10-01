<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class BrochureLeadModel extends Model
{
    protected $table         = 'brochure_leads';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    // Written once and never edited: a lead is a record of what happened.
    protected $updatedField  = '';

    protected $allowedFields = [
        'brochure_id', 'brochure_title', 'customer_id',
        'name', 'email', 'phone', 'notes',
        'source_type', 'source_id', 'source_url',
        'ip_address', 'user_agent',
    ];

    /**
     * Name and phone are the mandatory pair; email is optional.
     *
     * It is still VALIDATED when given — an address that cannot receive mail
     * is worse than none, because it looks like a usable lead in the listing.
     */
    protected $validationRules = [
        'id'    => 'permit_empty|is_natural_no_zero',
        'name'  => 'required|min_length[2]|max_length[191]',
        'email' => 'permit_empty|valid_email|max_length[191]',
        'phone' => 'required|min_length[6]|max_length[40]',
        'notes' => 'permit_empty|max_length[2000]',
    ];

    protected $validationMessages = [
        'name'  => ['required' => 'Please tell us your name.'],
        'email' => ['valid_email' => 'That does not look like an email address.'],
        'phone' => ['required' => 'Please give us a phone number.'],
    ];

    /**
     * Leads, newest first, with the brochure's current title where it survives.
     *
     * LEFT JOIN, not INNER: `brochure_id` is SET NULL when a brochure is
     * deleted and the lead outlives it. An inner join would silently hide
     * exactly the leads nobody can re-derive.
     *
     * @return array<int, array<string, mixed>>
     */
    public function page(int $perPage, int $offset, array $filters = []): array
    {
        return $this->applyFilters($filters)
            ->select('brochure_leads.*, b.title AS current_title, b.deleted_at AS brochure_deleted')
            ->join('brochures b', 'b.id = brochure_leads.brochure_id', 'left')
            ->orderBy('brochure_leads.created_at', 'DESC')
            ->orderBy('brochure_leads.id', 'DESC')
            ->findAll($perPage, $offset);
    }

    public function countFiltered(array $filters = []): int
    {
        return $this->applyFilters($filters)->countAllResults();
    }

    /**
     * Shared conditions, so the count and the page can never disagree — a
     * listing saying "42 leads" above 12 rows is the usual result of two
     * separate condition blocks.
     *
     * @return $this
     */
    private function applyFilters(array $filters): self
    {
        $brochureId = (int) ($filters['brochure'] ?? 0);

        if ($brochureId > 0) {
            $this->where('brochure_leads.brochure_id', $brochureId);
        }

        $term = trim((string) ($filters['q'] ?? ''));

        if ($term !== '') {
            // Bound, never interpolated: this reaches raw SQL.
            $this->groupStart()
                ->like('brochure_leads.name', $term)
                ->orLike('brochure_leads.email', $term)
                ->orLike('brochure_leads.phone', $term)
                ->groupEnd();
        }

        return $this;
    }
}
