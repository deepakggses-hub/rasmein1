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

    // ---- Customer accounts (Phase 5) ----
    // Public: this is how you get past the customerAuth filter.
    $routes->match(['GET', 'HEAD'], 'account/login', 'Account::showLogin', ['as' => 'login']);
    $routes->post('account/login', 'Account::login');
    $routes->post('account/logout', 'Account::logout');
    $routes->match(['GET', 'HEAD'], 'account/register', 'Account::showRegister');
    $routes->post('account/register', 'Account::register');
    $routes->match(['GET', 'HEAD'], 'account/forgot', 'Account::showForgot');
    $routes->post('account/forgot', 'Account::sendReset');
    $routes->match(['GET', 'HEAD'], 'account/reset/(:segment)', 'Account::showReset/$1');
    $routes->post('account/reset', 'Account::doReset');

    // Must come last in this group: a bare segment would otherwise swallow
    // 'shop/anything' before the more specific routes above are reached.
    $routes->match(['GET', 'HEAD'], 'shop/(:segment)', 'Shop::category/$1', ['as' => 'category']);
});

// =====================================================================
// CUSTOMER ACCOUNT AREA — everything behind the customerAuth filter.
// Every query inside is scoped to session('customer_id'); no owner is ever
// taken from the URL.
// =====================================================================
$routes->group('', [
    'namespace' => 'App\Controllers\Storefront',
    'filter'    => 'customerAuth',
], static function (RouteCollection $routes): void {
    $routes->match(['GET', 'HEAD'], 'account', 'AccountArea::dashboard', ['as' => 'account']);
    $routes->post('account/details', 'AccountArea::saveDetails');
    $routes->post('account/password', 'AccountArea::changePassword');

    $routes->match(['GET', 'HEAD'], 'account/orders', 'AccountArea::orders');
    $routes->match(['GET', 'HEAD'], 'account/orders/(:segment)', 'AccountArea::order/$1');

    $routes->match(['GET', 'HEAD'], 'account/addresses', 'AccountArea::addresses');
    $routes->post('account/addresses', 'AccountArea::saveAddress');
    $routes->post('account/addresses/delete', 'AccountArea::deleteAddress');
    $routes->post('account/addresses/default', 'AccountArea::makeDefaultAddress');

    $routes->match(['GET', 'HEAD'], 'wishlist', 'AccountArea::wishlist');
    $routes->post('wishlist/toggle', 'AccountArea::toggleWishlist');
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

    // ---- People, content, insight (Phase 4d) ----
    $routes->match(['GET', 'HEAD'], 'customers', 'Customers::index', ['filter' => 'adminAuth:customers.view']);
    $routes->match(['GET', 'HEAD'], 'customers/(:any)', 'Customers::show/$1', ['filter' => 'adminAuth:customers.view']);

    $routes->match(['GET', 'HEAD'], 'banners', 'Banners::index', ['filter' => 'adminAuth:content.manage']);
    $routes->match(['GET', 'HEAD'], 'banners/new', 'Banners::create', ['filter' => 'adminAuth:content.manage']);
    $routes->post('banners', 'Banners::store', ['filter' => 'adminAuth:content.manage']);
    $routes->match(['GET', 'HEAD'], 'banners/(:num)/edit', 'Banners::edit/$1', ['filter' => 'adminAuth:content.manage']);
    $routes->post('banners/(:num)', 'Banners::update/$1', ['filter' => 'adminAuth:content.manage']);
    $routes->post('banners/(:num)/delete', 'Banners::delete/$1', ['filter' => 'adminAuth:content.manage']);

    $routes->match(['GET', 'HEAD'], 'reports', 'Reports::index', ['filter' => 'adminAuth:reports.view']);
    $routes->match(['GET', 'HEAD'], 'reports/export/(:segment)', 'Reports::export/$1', ['filter' => 'adminAuth:reports.view']);

    $routes->match(['GET', 'HEAD'], 'staff', 'Staff::index', ['filter' => 'adminAuth:staff.manage']);
    $routes->match(['GET', 'HEAD'], 'staff/new', 'Staff::create', ['filter' => 'adminAuth:staff.manage']);
    $routes->post('staff', 'Staff::store', ['filter' => 'adminAuth:staff.manage']);
    $routes->match(['GET', 'HEAD'], 'staff/(:num)/edit', 'Staff::edit/$1', ['filter' => 'adminAuth:staff.manage']);
    $routes->post('staff/(:num)', 'Staff::update/$1', ['filter' => 'adminAuth:staff.manage']);
    $routes->post('staff/(:num)/delete', 'Staff::delete/$1', ['filter' => 'adminAuth:staff.manage']);

    // ---- Notifications & email templates (Phase 6) ----
    // The notification centre needs no extra permission: everyone sees only
    // their own rows, and those were targeted by permission when created.
    $routes->match(['GET', 'HEAD'], 'notifications', 'Notifications::index');
    $routes->post('notifications/(:num)/read', 'Notifications::read/$1');
    $routes->post('notifications/read-all', 'Notifications::readAll');

    $routes->match(['GET', 'HEAD'], 'email-templates', 'EmailTemplates::index', ['filter' => 'adminAuth:content.manage']);
    $routes->match(['GET', 'HEAD'], 'email-templates/(:num)/edit', 'EmailTemplates::edit/$1', ['filter' => 'adminAuth:content.manage']);
    $routes->post('email-templates/(:num)', 'EmailTemplates::update/$1', ['filter' => 'adminAuth:content.manage']);
    $routes->post('email-templates/(:num)/test', 'EmailTemplates::test/$1', ['filter' => 'adminAuth:content.manage']);
    $routes->post('email-templates/restore', 'EmailTemplates::restore', ['filter' => 'adminAuth:content.manage']);

    // Image upload from inside the rich text editor. Permission is checked in
    // the controller, because either content.manage or products.manage is
    // enough — a product editor needs images too.
    $routes->post('editor/upload', 'EditorUpload::store');

    // ---- Settings ----
    $routes->match(['GET', 'HEAD'], 'settings', 'Settings::index');
    $routes->post('settings', 'Settings::update', ['filter' => 'adminAuth:settings.manage']);
    // The master switch names its own permission, separate from settings.manage.
    $routes->post('settings/journey', 'Settings::switchJourney', ['filter' => 'adminAuth:settings.journey_mode']);

    // ---- Audit ----
    $routes->match(['GET', 'HEAD'], 'audit', 'Audit::index', ['filter' => 'adminAuth:audit.view']);
});
