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
        /*
         * The homepage is the ordinary shop.
         *
         * Someone who navigates back here from /corporate has left the
         * corporate journey; keeping the switch on would show them enquiry
         * buttons on a page selling personalised gifts.
         */
        service('settings')->setJourneyMode(\Config\Rasmein::MODE_BUY);

        $products = model(ProductModel::class);
        $boxes    = model(GiftBoxModel::class);
        $banners  = model(BannerModel::class);

        return $this->page('storefront/home', [
            // A slider, so several banners rather than one. It degrades to a
            // single static hero when a shop has only uploaded one.
            'heroSlides'   => $banners->liveFor('home_hero', 5),
            'feature'      => $banners->liveFor('home_feature', 1)[0] ?? null,
            'clients'      => $banners->liveFor('home_client', 12),
            'gallery'      => $banners->liveFor('home_gallery', 12),
            'strip'        => $banners->liveFor('home_strip', 3),

            'giftBoxes'    => $boxes->featured(3),
            'boxCount'     => $boxes->where('is_active', 1)->countAllResults(),
            'featured'     => $products->featured(8),
            'newArrivals'  => $products->latest(4),
            'trayProducts' => $products->giftBoxEligible(6),

            // Six tiles in the design's collections mosaic.
            'categories'   => model(CategoryModel::class)->withProductCounts(true, 6),
            'collections'  => model(CollectionModel::class)->featured(3),
            'occasions'    => model(CollectionModel::class)->liveOccasions(10),

            'testimonials' => model(\App\Models\TestimonialModel::class)->live(),
            'reviewStats'  => model(\App\Models\TestimonialModel::class)->summary(),

            // Editable copy. Read once here rather than scattered through the
            // view, so the template stays about layout.
            'copy'         => $this->homeCopy(),
        ], [
            'title'       => $this->brand->brandName . ' — ' . $this->brand->brandTagline,
            'description' => 'Build a gift box compartment by compartment, or choose from '
                . 'ready-to-send hampers. Handpicked, thoughtfully packed, delivered across India.',
        ]);
    }

    /**
     * The homepage's editable copy, read in one query.
     *
     * Raw, not via SettingsService::get(), because a deliberately blank value
     * means "hide this section" and get() treats blank as absent.
     *
     * @return array<string, string>
     */
    private function homeCopy(): array
    {
        $copy = [];

        try {
            foreach (db_connect()->table('settings')
                ->select('key_name, value')
                ->where('group_name', 'home')
                ->get()->getResultArray() as $row) {
                $copy[$row['key_name']] = (string) $row['value'];
            }
        } catch (\Throwable) {
            // A missing seed must not take the homepage down; the view falls
            // back to shipped wording for anything absent.
        }

        return $copy;
    }
}
