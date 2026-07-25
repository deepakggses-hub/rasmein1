<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\GiftBoxModel;

/** Step 1 of the gifting flow: choose a box. */
class GiftBoxes extends StorefrontController
{
    public function index(): string
    {
        return $this->page('storefront/gift_boxes', [
            'boxes'  => model(GiftBoxModel::class)->activeBoxes(),
            'crumbs' => [['label' => 'Gift boxes', 'url' => null]],
        ], [
            'title'       => 'Build your own gift box · ' . $this->brand->brandName,
            'description' => 'Choose a box by size, theme or budget, then fill each '
                . 'compartment yourself. Personalise it, and send it as it is.',
        ]);
    }
}
