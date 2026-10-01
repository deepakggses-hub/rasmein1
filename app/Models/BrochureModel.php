<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class BrochureModel extends Model
{
    protected $table         = 'brochures';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'title', 'description', 'path', 'filename', 'mime', 'bytes',
        'is_default', 'is_active', 'download_count',
    ];

    /**
     * `id` is declared so it can be used as a {placeholder}.
     *
     * CI4 4.7 throws "No validation rules for the placeholder" without it, and
     * every edit form on the model 500s. Nine models needed this already.
     */
    protected $validationRules = [
        'id'       => 'permit_empty|is_natural_no_zero',
        'title'    => 'required|min_length[2]|max_length[191]',
        'path'     => 'required|max_length[255]',
        'filename' => 'required|max_length[191]',
    ];

    protected $validationMessages = [
        'title' => ['required' => 'Give the brochure a title — it is what the button says.'],
    ];

    /**
     * The fallback brochure, for a page with nothing of its own.
     *
     * @return array<string, mixed>|null
     */
    public function default(): ?array
    {
        return $this->where('is_default', 1)->where('is_active', 1)->first();
    }

    /**
     * Make one brochure the default and demote every other.
     *
     * Done in one place rather than at each call site: two defaults is a state
     * with no correct answer, and the screen would then show whichever the
     * database happened to return first.
     */
    public function makeDefault(int $id): void
    {
        $this->db->table($this->table)->where('id !=', $id)->update(['is_default' => 0]);
        $this->db->table($this->table)->where('id', $id)->update(['is_default' => 1]);
    }

    /**
     * Cheap counter for the listing. The leads table is the real record.
     *
     * A raw increment rather than read-then-write, so two people downloading
     * at once cannot both read 5 and both write 6.
     */
    public function countDownload(int $id): void
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->set('download_count', 'download_count + 1', false)
            ->update();
    }
}
