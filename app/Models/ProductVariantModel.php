<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ProductVariantModel extends Model
{
    protected $table         = 'product_variants';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'id', 'product_id', 'sku', 'variant_key', 'label', 'price', 'compare_at_price',
        'description', 'image', 'stock_qty', 'is_default', 'is_active', 'sort_order',
    ];

    protected $validationRules = [
        'id'          => 'permit_empty|is_natural_no_zero',
        'product_id'  => 'required|is_natural_no_zero',
        'sku'         => 'required|max_length[80]|is_unique[product_variants.sku,id,{id}]',
        'variant_key' => 'required|max_length[120]',
    ];

    /**
     * Everything the product page needs to offer variants.
     *
     * Returns the variants, the attribute values that appear across them, and
     * the value ids each variant is made of. The page can then answer "if silver
     * is chosen, which sizes remain" without another request — which is what
     * makes the selection feel instant rather than a page load per click.
     *
     * @return array{variants: array<int, array<string, mixed>>, groups: array<string, mixed>}
     */
    public function matrixFor(int $productId): array
    {
        $variants = $this->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('is_default', 'DESC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        if ($variants === []) {
            return ['variants' => [], 'groups' => []];
        }

        // One query for every value across every variant, rather than one per
        // variant — a piece with twelve variants would otherwise cost twelve.
        $rows = $this->db->table('variant_values vv')
            ->select('vv.variant_id, av.id AS value_id, av.label, av.swatch_hex,'
                . ' a.code, a.name AS attribute, a.input_type, a.sort_order', false)
            ->join('attribute_values av', 'av.id = vv.value_id')
            ->join('attributes a', 'a.id = av.attribute_id')
            ->whereIn('vv.variant_id', array_column($variants, 'id'))
            ->orderBy('a.sort_order', 'ASC')
            ->orderBy('av.sort_order', 'ASC')
            ->get()->getResultArray();

        $byVariant = [];
        $groups    = [];

        foreach ($rows as $row) {
            $byVariant[(int) $row['variant_id']][(string) $row['code']] = (int) $row['value_id'];

            $code = (string) $row['code'];

            $groups[$code] ??= [
                'code'       => $code,
                'name'       => (string) $row['attribute'],
                'input_type' => (string) $row['input_type'],
                'values'     => [],
            ];

            // Keyed by id so a value shared by six variants is listed once.
            $groups[$code]['values'][(int) $row['value_id']] = [
                'id'     => (int) $row['value_id'],
                'label'  => (string) $row['label'],
                'swatch' => $row['swatch_hex'],
            ];
        }

        foreach ($groups as $code => $group) {
            $groups[$code]['values'] = array_values($group['values']);
        }

        foreach ($variants as $i => $variant) {
            $variants[$i]['values'] = $byVariant[(int) $variant['id']] ?? [];
        }

        return ['variants' => $variants, 'groups' => $groups];
    }

    /**
     * One variant by its URL key.
     *
     * @return array<string, mixed>|null
     */
    public function byKey(int $productId, string $key): ?array
    {
        return $this->where('product_id', $productId)
            ->where('variant_key', $key)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * The one to show when no variant was asked for.
     *
     * Prefers the default, then anything in stock, then simply the first — a
     * page must never open on nothing just because the default sold out.
     *
     * @param array<int, array<string, mixed>> $variants
     *
     * @return array<string, mixed>|null
     */
    public function pick(array $variants): ?array
    {
        if ($variants === []) {
            return null;
        }

        foreach ($variants as $variant) {
            if ((int) $variant['is_default'] === 1 && (int) $variant['stock_qty'] > 0) {
                return $variant;
            }
        }

        foreach ($variants as $variant) {
            if ((int) $variant['stock_qty'] > 0) {
                return $variant;
            }
        }

        return $variants[0];
    }

    /**
     * A variant's effective values, falling back to the product.
     *
     * The fallback is the whole point of the nullable columns: a variant that
     * differs only in colour should not have to restate the price, the picture
     * and the description — and when the product's price changes, it follows.
     *
     * @param array<string, mixed> $variant
     *
     * @return array<string, mixed>
     */
    public function resolve(array $variant, object $product): array
    {
        return [
            'id'          => (int) $variant['id'],
            'sku'         => (string) $variant['sku'],
            'key'         => (string) $variant['variant_key'],
            'label'       => (string) $variant['label'],
            'price'       => $variant['price'] !== null ? (float) $variant['price'] : (float) $product->price,
            'compare_at'  => $variant['compare_at_price'] !== null
                ? (float) $variant['compare_at_price']
                : ($product->compare_at_price !== null ? (float) $product->compare_at_price : null),
            'description' => $variant['description'] ?: $product->description,
            'image'       => $variant['image'] ?: null,
            'stock'       => (int) $variant['stock_qty'],
            'in_stock'    => (int) $variant['stock_qty'] > 0,
        ];
    }
}
