<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * Route map.
 *
 * Auto-routing is OFF (see Config/Routing.php). Nothing is reachable unless it
 * is listed here, so a controller cannot be exposed by accident.
 *
 * Groups mirror the namespaces: Storefront / Admin / Api.
 *
 * Public pages are registered for GET *and* HEAD. CodeIgniter does not fall
 * back from HEAD to GET, so a HEAD-only client — most uptime monitors, some
 * CDNs, link checkers — would otherwise get a 404 on a page that works fine.
 */

/** @var RouteCollection $routes */

// =====================================================================
// STOREFRONT — public
// =====================================================================
$routes->group('', ['namespace' => 'App\Controllers\Storefront'], static function (RouteCollection $routes): void {
    $routes->match(['GET', 'HEAD'], '/', 'Home::index', ['as' => 'home']);

    // CMS pages (about, shipping, returns…)
    $routes->match(['GET', 'HEAD'], 'page/(:segment)', 'Pages::show/$1', ['as' => 'page']);

    // ---- Catalogue (Phase 2) ----
    $routes->match(['GET', 'HEAD'], 'shop', 'Shop::index', ['as' => 'shop']);
    $routes->match(['GET', 'HEAD'], 'search', 'Shop::search', ['as' => 'search']);
    $routes->match(['GET', 'HEAD'], 'collections', 'Collections::index', ['as' => 'collections']);
    $routes->match(['GET', 'HEAD'], 'collections/(:segment)', 'Shop::collection/$1', ['as' => 'collection']);
    $routes->match(['GET', 'HEAD'], 'product/(:segment)', 'Products::show/$1', ['as' => 'product']);

    // Must come last in this group: a bare segment would otherwise swallow
    // 'shop/anything' before the more specific routes above are reached.
    $routes->match(['GET', 'HEAD'], 'shop/(:segment)', 'Shop::category/$1', ['as' => 'category']);
});

// =====================================================================
// ROADMAP — destinations whose feature ships in a later phase.
//
// These keep navigation honest while the build is in progress. Delete a line
// here at the same moment you add the real route above it. Arguments are
// literals from this file, never user input.
// =====================================================================
$routes->group('', ['namespace' => 'App\Controllers\Storefront'], static function (RouteCollection $routes): void {
    // --- Phase 2: catalogue, cart, checkout ---
    $routes->match(['GET', 'HEAD'], 'cart', 'Roadmap::show/2/your-cart');
    $routes->match(['GET', 'HEAD'], 'checkout', 'Roadmap::show/2/checkout');

    // --- Phase 3: gift-box builder ---
    $routes->match(['GET', 'HEAD'], 'gift-boxes', 'Roadmap::show/3/gift-boxes');
    $routes->match(['GET', 'HEAD'], 'gift-box/(:segment)', 'Roadmap::show/3/gift-boxes');
    $routes->match(['GET', 'HEAD'], 'build', 'Roadmap::show/3/the-gift-box-builder');
    $routes->match(['GET', 'HEAD'], 'build/(:segment)', 'Roadmap::show/3/the-gift-box-builder');
    $routes->match(['GET', 'HEAD'], 'enquiry', 'Roadmap::show/3/your-enquiry-list');

    // --- Phase 5: customer accounts ---
    $routes->match(['GET', 'HEAD'], 'wishlist', 'Roadmap::show/5/wishlist');
    $routes->match(['GET', 'HEAD'], 'account', 'Roadmap::show/5/your-account');
    $routes->match(['GET', 'HEAD'], 'account/login', 'Roadmap::show/5/sign-in');
    $routes->match(['GET', 'HEAD'], 'account/register', 'Roadmap::show/5/create-an-account');
});

// =====================================================================
// ADMIN — every route behind the adminAuth filter except the login screen.
// Built out in Phase 4.
// =====================================================================
$routes->group('admin', ['namespace' => 'App\Controllers\Storefront'], static function (RouteCollection $routes): void {
    $routes->match(['GET', 'HEAD'], '/', 'Roadmap::show/4/the-admin-panel');
    $routes->match(['GET', 'HEAD'], 'login', 'Roadmap::show/4/admin-sign-in');
});
