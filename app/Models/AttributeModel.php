<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Attributes — colour, size, shape, finish — and their permitted values.
 */
class AttributeModel extends Model
{
    protected $table         = 'attributes';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'id', 'name', 'code', 'input_type', 'is_selectable', 'is_filterable',
        'sort_order', 'is_active',
    ];

    protected $validationRules = [
        // Needed by is_unique[...,id,{id}]: without it the rule cannot exclude
        // the row being edited and every update fails as a duplicate.
        'id'   => 'permit_empty|is_natural_no_zero',
        'name' => 'required|max_length[80]',
        'code' => 'required|max_length[40]|alpha_dash|is_unique[attributes.code,id,{id}]',
    ];

    /**
     * Every attribute with its values, ready to render.
     *
     * One query for the attributes and one for all their values — not one per
     * attribute. A product form showing six attributes should not cost seven
     * round trips.
     *
     * @return array<int, array<string, mixed>>
     */
    public function withValues(bool $activeOnly = true): array
    {
        $builder = $this->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC');

        if ($activeOnly) {
            $builder->where('is_active', 1);
        }

        $attributes = $builder->findAll();

        if ($attributes === []) {
            return [];
        }

        $values = model(AttributeValueModel::class)
            ->whereIn('attribute_id', array_column($attributes, 'id'))
            ->orderBy('sort_order', 'ASC')->orderBy('label', 'ASC')
            ->findAll();

        $byAttribute = [];

        foreach ($values as $value) {
            $byAttribute[(int) $value['attribute_id']][] = $value;
        }

        foreach ($attributes as $i => $attribute) {
            $attributes[$i]['values'] = $byAttribute[(int) $attribute['id']] ?? [];
        }

        return $attributes;
    }
}
