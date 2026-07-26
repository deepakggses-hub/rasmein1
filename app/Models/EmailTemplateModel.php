<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Editable email templates.
 *
 * `body_html` is authored by staff and is sanitised on save through the same
 * allowlist the CMS pages use — a template is rendered into an email that lands
 * in someone's inbox, so a script tag or a tracking iframe getting in there
 * matters just as much as on a web page.
 */
class EmailTemplateModel extends Model
{
    protected $table         = 'email_templates';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'template_key', 'name', 'description', 'audience',
        'subject', 'body_html', 'placeholders', 'is_system', 'is_active',
    ];

    protected $validationRules = [
        'id'           => 'permit_empty|is_natural_no_zero',
        'template_key' => 'required|max_length[80]|regex_match[/^[a-z0-9_]+$/]|is_unique[email_templates.template_key,id,{id}]',
        'name'         => 'required|max_length[120]',
        'audience'     => 'required|in_list[customer,admin]',
        'subject'      => 'required|max_length[255]',
    ];

    protected $beforeInsert = ['sanitiseBody'];
    protected $beforeUpdate = ['sanitiseBody'];

    protected function sanitiseBody(array $data): array
    {
        if (isset($data['data']['body_html'])) {
            $data['data']['body_html'] = service('sanitiser')->clean($data['data']['body_html']);
        }

        // A subject line is plain text. Strip any markup outright rather than
        // sanitising it — there is no legitimate HTML in a subject.
        if (isset($data['data']['subject'])) {
            $data['data']['subject'] = trim(strip_tags((string) $data['data']['subject']));
        }

        return $data;
    }

    public function findByKey(string $key): ?array
    {
        return $this->where('template_key', $key)->first();
    }

    /** @return array<string, array<int, array<string, mixed>>> Grouped by audience. */
    public function grouped(): array
    {
        $out = ['customer' => [], 'admin' => []];

        foreach ($this->orderBy('audience', 'ASC')->orderBy('name', 'ASC')->findAll() as $row) {
            $out[$row['audience']][] = $row;
        }

        return $out;
    }

    /** @return array<string, string> token => description */
    public function placeholdersFor(array $template): array
    {
        $decoded = json_decode((string) ($template['placeholders'] ?? '[]'), true);

        return is_array($decoded) ? $decoded : [];
    }
}
