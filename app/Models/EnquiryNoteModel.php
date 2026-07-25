<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EnquiryNoteModel extends Model
{
    protected $table         = 'enquiry_notes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = ['enquiry_id', 'admin_user_id', 'note', 'note_type', 'is_internal'];

    protected $validationRules = [
        'enquiry_id' => 'required|is_natural_no_zero',
        'note'       => 'required|max_length[2000]',
        'note_type'  => 'permit_empty|in_list[note,call,email,meeting,quote]',
    ];

    /** @return array<int, array<string, mixed>> */
    public function forEnquiry(int $enquiryId): array
    {
        return $this->select('enquiry_notes.*, admin_users.name AS author')
            ->join('admin_users', 'admin_users.id = enquiry_notes.admin_user_id', 'left')
            ->where('enquiry_id', $enquiryId)
            ->orderBy('enquiry_notes.id', 'DESC')
            ->findAll();
    }
}
