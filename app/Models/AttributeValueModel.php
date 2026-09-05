<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class AttributeValueModel extends Model
{
    protected $table         = 'attribute_values';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = ['id', 'attribute_id', 'label', 'swatch_hex', 'sort_order'];

    protected $validationRules = [
        'id'           => 'permit_empty|is_natural_no_zero',
        'attribute_id' => 'required|is_natural_no_zero',
        'label'        => 'required|max_length[80]',
        // A loose hex would render as a transparent chip and look like a bug.
        'swatch_hex'   => 'permit_empty|regex_match[/^#[0-9a-fA-F]{6}$/]',
    ];

    /**
     * The attributes carried by a set of products, in ONE query.
     *
     * A listing of fifty cards asking per product is fifty queries; this is the
     * same shape as ProductModel::imagesFor().
     *
     * @param list<int> $productIds
     *
     * @return array<int, array<int, array<string, mixed>>> product id => rows
     */
    public function forProducts(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if ($ids === []) {
            return [];
        }

        $rows = $this->db->table('product_attributes pa')
            ->select('pa.product_id, av.id AS value_id, av.label, av.swatch_hex,'
                . ' a.id AS attribute_id, a.name AS attribute, a.code, a.input_type, a.is_selectable', false)
            ->join('attribute_values av', 'av.id = pa.value_id')
            ->join('attributes a', 'a.id = av.attribute_id')
            ->whereIn('pa.product_id', $ids)
            ->where('a.is_active', 1)
            ->orderBy('a.sort_order', 'ASC')
            ->orderBy('pa.sort_order', 'ASC')
            ->orderBy('av.label', 'ASC')
            ->get()->getResultArray();

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row['product_id']][] = $row;
        }

        return $out;
    }

    /**
     * One product's attributes, grouped by attribute.
     *
     * @return array<string, array<string, mixed>>
     */
    public function grouped(int $productId): array
    {
        $rows = $this->forProducts([$productId])[$productId] ?? [];
        $out  = [];

        foreach ($rows as $row) {
            $code = (string) $row['code'];

            $out[$code] ??= [
                'name'       => $row['attribute'],
                'code'       => $code,
                'input_type' => $row['input_type'],
                'selectable' => (int) $row['is_selectable'] === 1,
                'values'     => [],
            ];

            $out[$code]['values'][] = $row;
        }

        return $out;
    }
}
