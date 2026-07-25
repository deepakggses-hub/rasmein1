<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\CollectionModel;

class Collections extends StorefrontController
{
    public function index(): string
    {
        return $this->page('storefront/collections', [
            'collections' => model(CollectionModel::class)
                ->where('is_active', 1)
                ->orderBy('sort_order', 'ASC')
                ->findAll(),
            'crumbs' => [['label' => 'Collections', 'url' => null]],
        ], [
            'title'       => 'Collections · ' . $this->brand->brandName,
            'description' => 'Gift boxes and edits built around a single moment — Diwali, a new home, a tea drinker.',
        ]);
    }
}
