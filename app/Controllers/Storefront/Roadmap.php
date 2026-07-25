<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

/**
 * Placeholder for routes whose feature ships in a later phase.
 *
 * Every navigation destination resolves to something branded and honest
 * during the build, rather than a 404. As each phase lands, its routes are
 * pointed at the real controller and removed from Routes.php's roadmap group.
 *
 * The phase and title come from the route definition, never from user input.
 */
class Roadmap extends StorefrontController
{
    public function show(string $phase = '2', string $title = 'This section'): string
    {
        $milestones = [
            '2' => 'Catalogue, product pages, cart and checkout',
            '3' => 'Gift-box builder and the Buy / Enquire switch',
            '4' => 'Admin panel',
            '5' => 'Customer accounts',
        ];

        return $this->page('storefront/roadmap', [
            'phase'      => $phase,
            'pageTitle'  => str_replace('-', ' ', $title),
            'milestone'  => $milestones[$phase] ?? 'A later phase',
        ], [
            'title'   => ucfirst(str_replace('-', ' ', $title)) . ' · ' . $this->brand->brandName,
            'noindex' => true,
        ]);
    }
}
