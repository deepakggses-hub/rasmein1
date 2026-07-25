<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ProductImageModel extends Model
{
    protected $table         = 'product_images';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = ['product_id', 'path', 'alt_text', 'is_primary', 'sort_order'];

    protected $validationRules = [
        'product_id' => 'required|is_natural_no_zero',
        'path'       => 'required|max_length[255]',
        'alt_text'   => 'permit_empty|max_length[191]',
    ];

    /** @return array<int, array<string, mixed>> */
    public function forProduct(int $productId): array
    {
        return $this->where('product_id', $productId)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('sort_order', 'ASC')
            ->findAll();
    }

    /** Exactly one image per product may be primary. */
    public function makePrimary(int $productId, int $imageId): void
    {
        $this->where('product_id', $productId)->set('is_primary', 0)->update();
        $this->update($imageId, ['is_primary' => 1]);
    }
}
