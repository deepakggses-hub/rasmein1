<?php
declare(strict_types=1);
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class Diag extends BaseCommand
{
    protected $group = 'Rasmein';
    protected $name = 'rasmein:diag';
    protected $description = 'Smoke-test the storefront queries.';

    public function run(array $params)
    {
        $checks = [
            'categories.activeTopLevel'  => fn () => count(model(\App\Models\CategoryModel::class)->activeTopLevel()),
            'categories.withCounts'      => fn () => count(model(\App\Models\CategoryModel::class)->withProductCounts(true, 6)),
            'products.featured'          => fn () => count(model(\App\Models\ProductModel::class)->featured(8)),
            'products.latest'            => fn () => count(model(\App\Models\ProductModel::class)->latest(4)),
            'products.giftBoxEligible'   => fn () => count(model(\App\Models\ProductModel::class)->giftBoxEligible(6)),
            'giftboxes.featured'         => fn () => count(model(\App\Models\GiftBoxModel::class)->featured(3)),
            'giftboxes.count'            => fn () => model(\App\Models\GiftBoxModel::class)->where('is_active', 1)->countAllResults(),
            'giftboxes.allowedProducts'  => fn () => count(model(\App\Models\GiftBoxModel::class)->allowedProductIds(2)),
            'collections.featured'       => fn () => count(model(\App\Models\CollectionModel::class)->featured(3)),
            'banners.hero'               => fn () => count(model(\App\Models\BannerModel::class)->liveFor('home_hero', 1)),
            'pages.footerLinks'          => fn () => count(model(\App\Models\PageModel::class)->footerLinks()),
            'settings.journeyMode'       => fn () => service('settings')->journeyMode(),
        ];

        foreach ($checks as $label => $check) {
            try {
                CLI::write(sprintf('  %-28s OK  %s', $label, (string) $check()), 'green');
            } catch (\Throwable $e) {
                CLI::write(sprintf('  %-28s FAIL', $label), 'red');
                CLI::write('      ' . $e->getMessage(), 'yellow');
            }
        }
    }
}
