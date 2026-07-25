<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\BannerModel;
use App\Models\CategoryModel;
use App\Models\CollectionModel;
use App\Models\GiftBoxModel;
use App\Models\ProductModel;

class Home extends StorefrontController
{
    public function index(): string
    {
        $products = model(ProductModel::class);
        $boxes    = model(GiftBoxModel::class);

        return $this->page('storefront/home', [
            'hero'         => model(BannerModel::class)->liveFor('home_hero', 1)[0] ?? null,
            'strip'        => model(BannerModel::class)->liveFor('home_strip', 3),
            'giftBoxes'    => $boxes->featured(3),
            'boxCount'     => $boxes->where('is_active', 1)->countAllResults(),
            'featured'     => $products->featured(8),
            'newArrivals'  => $products->latest(4),
            // Thumbnails that fill the hero tray — real products, not stock art.
            'trayProducts' => $products->giftBoxEligible(6),
            'categories'   => model(CategoryModel::class)->withProductCounts(true, 6),
            'collections'  => model(CollectionModel::class)->featured(3),
        ], [
            'title'       => $this->brand->brandName . ' — ' . $this->brand->brandTagline,
            'description' => 'Build a gift box compartment by compartment, or choose from '
                . 'ready-to-send hampers. Handpicked, thoughtfully packed, delivered across India.',
        ]);
    }
}
