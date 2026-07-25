<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lead-tracking fields for an order submitted in Enquire mode. One row per
 * order where journey_mode = 'enquire_now'.
 */
class EnquiryModel extends Model
{
    protected $table          = 'enquiries';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'order_id', 'enquiry_ref', 'lead_status', 'source', 'company',
        'preferred_contact', 'requirement_note', 'expected_quantity',
        'needed_by', 'estimated_value', 'quoted_value',
        'assigned_to_admin_id', 'followup_at', 'closed_at', 'lost_reason', 'spam_score',
    ];

    protected $validationRules = [
        'order_id'          => 'required|is_natural_no_zero',
        'lead_status'        => 'required|in_list[new,contacted,quoted,won,lost,spam]',
        'preferred_contact'  => 'permit_empty|in_list[email,phone,whatsapp]',
        'requirement_note'  => 'permit_empty|max_length[2000]',
        'company'           => 'permit_empty|max_length[120]',
    ];

    public function buildRef(int $id): string
    {
        return sprintf('ENQ-%s-%06d', date('Y'), $id);
    }

    public function findByOrderId(int $orderId): ?array
    {
        return $this->where('order_id', $orderId)->first();
    }
}
