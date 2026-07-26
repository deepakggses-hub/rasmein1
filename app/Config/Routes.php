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

    // ---- Gift-box builder (Phase 3) ----
    $routes->match(['GET', 'HEAD'], 'gift-boxes', 'GiftBoxes::index', ['as' => 'giftboxes']);
    $routes->match(['GET', 'HEAD'], 'build', 'Builder::index');
    $routes->match(['GET', 'HEAD'], 'build/box/(:num)', 'Builder::show/$1', ['as' => 'builder']);
    $routes->match(['GET', 'HEAD'], 'build/(:segment)', 'Builder::start/$1');
    $routes->post('build/box/(:num)/add', 'Builder::add/$1');
    $routes->post('build/box/(:num)/quantity', 'Builder::setQuantity/$1');
    $routes->post('build/box/(:num)/remove', 'Builder::remove/$1');
    $routes->post('build/box/(:num)/clear', 'Builder::clear/$1');
    $routes->post('build/box/(:num)/discard', 'Builder::discard/$1');
    $routes->post('build/box/(:num)/personalise', 'Builder::personalise/$1');
    $routes->post('build/box/(:num)/finish', 'Builder::finish/$1');

    // ---- Cart & checkout (Phase 2b) ----
    $routes->match(['GET', 'HEAD'], 'cart', 'Cart::show', ['as' => 'cart']);
    // In Enquire mode the same page is the enquiry list.
    $routes->match(['GET', 'HEAD'], 'enquiry', 'Cart::show');
    $routes->post('cart/add', 'Cart::add');
    $routes->post('cart/update', 'Cart::update');
    $routes->post('cart/remove', 'Cart::remove');
    $routes->post('cart/coupon', 'Cart::applyCoupon');
    $routes->post('cart/coupon/remove', 'Cart::removeCoupon');

    $routes->match(['GET', 'HEAD'], 'checkout', 'Checkout::show', ['as' => 'checkout']);
    $routes->post('checkout', 'Checkout::place');
    $routes->match(['GET', 'HEAD'], 'order/(:segment)', 'Checkout::confirmation/$1', ['as' => 'order']);

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

    // --- Phase 3: gift-box builder ---

    // --- Phase 5: customer accounts ---
    $routes->match(['GET', 'HEAD'], 'wishlist', 'Roadmap::show/5/wishlist');
    $routes->match(['GET', 'HEAD'], 'account', 'Roadmap::show/5/your-account');
    $routes->match(['GET', 'HEAD'], 'account/login', 'Roadmap::show/5/sign-in');
    $routes->match(['GET', 'HEAD'], 'account/register', 'Roadmap::show/5/create-an-account');
});

// =====================================================================
// ADMIN
//
// Sign-in and the password screen sit outside the auth filter — they are how
// you get past it. Everything else is behind `adminAuth`, and the routes that
// change something also name the permission they require, so authorisation is
// declared at the route AND re-checked in the controller.
// =====================================================================
$routes->group('admin', ['namespace' => 'App\Controllers\Admin'], static function (RouteCollection $routes): void {
    $routes->match(['GET', 'HEAD'], 'login', 'Auth::showLogin', ['as' => 'adminLogin']);
    $routes->post('login', 'Auth::login');
    $routes->post('logout', 'Auth::logout', ['filter' => 'adminAuth']);

    // Reachable while must_change_password is set, so it cannot lock anyone out.
    $routes->match(['GET', 'HEAD'], 'password', 'Auth::showPassword', ['filter' => 'adminAuth']);
    $routes->post('password', 'Auth::updatePassword', ['filter' => 'adminAuth']);
});

$routes->group('admin', [
    'namespace' => 'App\Controllers\Admin',
    'filter'    => 'adminAuth',
], static function (RouteCollection $routes): void {

    $routes->match(['GET', 'HEAD'], '/', 'Dashboard::index', ['as' => 'adminHome']);

    // ---- Orders ----
    $routes->match(['GET', 'HEAD'], 'orders', 'Orders::index');
    $routes->match(['GET', 'HEAD'], 'orders/(:num)', 'Orders::show/$1');
    $routes->post('orders/(:num)/status', 'Orders::updateStatus/$1');
    $routes->post('orders/(:num)/payment', 'Orders::updatePayment/$1');
    $routes->post('orders/(:num)/dispatch', 'Orders::dispatch/$1');
    $routes->post('orders/(:num)/note', 'Orders::addNote/$1');

    // ---- Enquiries ----
    $routes->match(['GET', 'HEAD'], 'enquiries', 'Enquiries::index');
    $routes->match(['GET', 'HEAD'], 'enquiries/(:num)', 'Enquiries::show/$1');
    $routes->post('enquiries/(:num)', 'Enquiries::update/$1');
    $routes->post('enquiries/(:num)/note', 'Enquiries::addNote/$1');

    // ---- Catalogue (Phase 4b) ----
    $routes->match(['GET', 'HEAD'], 'products', 'Products::index');
    $routes->match(['GET', 'HEAD'], 'products/new', 'Products::create', ['filter' => 'adminAuth:products.manage']);
    $routes->post('products', 'Products::store', ['filter' => 'adminAuth:products.manage']);
    $routes->match(['GET', 'HEAD'], 'products/(:num)/edit', 'Products::edit/$1', ['filter' => 'adminAuth:products.manage']);
    $routes->post('products/(:num)', 'Products::update/$1', ['filter' => 'adminAuth:products.manage']);
    $routes->post('products/(:num)/delete', 'Products::delete/$1', ['filter' => 'adminAuth:products.manage']);
    $routes->post('products/(:num)/images/(:num)/delete', 'Products::deleteImage/$1/$2', ['filter' => 'adminAuth:products.manage']);
    $routes->post('products/(:num)/images/(:num)/primary', 'Products::makePrimaryImage/$1/$2', ['filter' => 'adminAuth:products.manage']);

    $routes->match(['GET', 'HEAD'], 'categories', 'Categories::index');
    $routes->post('categories', 'Categories::store', ['filter' => 'adminAuth:categories.manage']);
    $routes->match(['GET', 'HEAD'], 'categories/(:num)/edit', 'Categories::edit/$1', ['filter' => 'adminAuth:categories.manage']);
    $routes->post('categories/(:num)', 'Categories::update/$1', ['filter' => 'adminAuth:categories.manage']);
    $routes->post('categories/(:num)/delete', 'Categories::delete/$1', ['filter' => 'adminAuth:categories.manage']);

    // ---- Gift boxes (Phase 4c) ----
    $routes->match(['GET', 'HEAD'], 'gift-boxes', 'GiftBoxes::index');
    $routes->match(['GET', 'HEAD'], 'gift-boxes/new', 'GiftBoxes::create', ['filter' => 'adminAuth:giftboxes.manage']);
    $routes->post('gift-boxes', 'GiftBoxes::store', ['filter' => 'adminAuth:giftboxes.manage']);
    $routes->match(['GET', 'HEAD'], 'gift-boxes/(:num)/edit', 'GiftBoxes::edit/$1', ['filter' => 'adminAuth:giftboxes.manage']);
    $routes->post('gift-boxes/(:num)', 'GiftBoxes::update/$1', ['filter' => 'adminAuth:giftboxes.manage']);
    $routes->post('gift-boxes/(:num)/contents', 'GiftBoxes::saveContents/$1', ['filter' => 'adminAuth:giftboxes.manage']);
    $routes->post('gift-boxes/(:num)/rules', 'GiftBoxes::saveRules/$1', ['filter' => 'adminAuth:giftboxes.manage']);
    $routes->post('gift-boxes/(:num)/delete', 'GiftBoxes::delete/$1', ['filter' => 'adminAuth:giftboxes.manage']);

    // ---- Coupons (Phase 4c) ----
    $routes->match(['GET', 'HEAD'], 'coupons', 'Coupons::index', ['filter' => 'adminAuth:coupons.manage']);
    $routes->match(['GET', 'HEAD'], 'coupons/new', 'Coupons::create', ['filter' => 'adminAuth:coupons.manage']);
    $routes->post('coupons', 'Coupons::store', ['filter' => 'adminAuth:coupons.manage']);
    $routes->match(['GET', 'HEAD'], 'coupons/(:num)/edit', 'Coupons::edit/$1', ['filter' => 'adminAuth:coupons.manage']);
    $routes->post('coupons/(:num)', 'Coupons::update/$1', ['filter' => 'adminAuth:coupons.manage']);
    $routes->post('coupons/(:num)/delete', 'Coupons::delete/$1', ['filter' => 'adminAuth:coupons.manage']);

    // ---- Pages (Phase 4c) ----
    $routes->match(['GET', 'HEAD'], 'pages', 'Pages::index', ['filter' => 'adminAuth:content.manage']);
    $routes->match(['GET', 'HEAD'], 'pages/new', 'Pages::create', ['filter' => 'adminAuth:content.manage']);
    $routes->post('pages', 'Pages::store', ['filter' => 'adminAuth:content.manage']);
    $routes->match(['GET', 'HEAD'], 'pages/(:num)/edit', 'Pages::edit/$1', ['filter' => 'adminAuth:content.manage']);
    $routes->post('pages/(:num)', 'Pages::update/$1', ['filter' => 'adminAuth:content.manage']);
    $routes->post('pages/(:num)/delete', 'Pages::delete/$1', ['filter' => 'adminAuth:content.manage']);

    // ---- Settings ----
    $routes->match(['GET', 'HEAD'], 'settings', 'Settings::index');
    $routes->post('settings', 'Settings::update', ['filter' => 'adminAuth:settings.manage']);
    // The master switch names its own permission, separate from settings.manage.
    $routes->post('settings/journey', 'Settings::switchJourney', ['filter' => 'adminAuth:settings.journey_mode']);

    // ---- Audit ----
    $routes->match(['GET', 'HEAD'], 'audit', 'Audit::index', ['filter' => 'adminAuth:audit.view']);
});
