<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table         = 'settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'group_name', 'key_name', 'value', 'value_type',
        'label', 'description', 'is_public', 'is_locked', 'sort_order',
    ];

    protected $validationRules = [
        'key_name'   => 'required|max_length[100]|regex_match[/^[a-z0-9_.]+$/]',
        'value_type' => 'required|in_list[string,int,decimal,bool,json]',
        'group_name' => 'required|max_length[60]',
    ];

    protected $validationMessages = [
        'key_name' => [
            'regex_match' => 'A setting key may only contain lowercase letters, numbers, dots and underscores.',
        ],
    ];

    /** @return array<int, array<string, mixed>> */
    public function inGroup(string $group): array
    {
        return $this->where('group_name', $group)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('key_name', 'ASC')
            ->findAll();
    }
}
