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

    /*
     * Contact gets a bare URL. It is linked from the header, the footer and
     * every email signature, and /page/contact reads like a filing system
     * rather than an address. The others keep the prefix — a policy page is
     * not somewhere anyone types.
     */
    $routes->match(['GET', 'HEAD'], 'contact', 'Pages::show/contact');

    // Address lookup, proxied and cached — see Storefront\Pincode.
    $routes->match(['GET', 'HEAD'], 'pincode/(:num)', 'Pincode::show/$1');

    // Switching between buying and enquiring. POST: it changes how the whole
    // site behaves, and a GET that does that can be fired by any image tag.
    $routes->post('mode', 'Mode::set');

    // The enquiry form on a content page. Feeds the same enquiries pipeline.
    $routes->post('enquiry/submit', 'Leads::submit');

    // ---- Catalogue (Phase 2) ----
    $routes->match(['GET', 'HEAD'], 'shop', 'Shop::index', ['as' => 'shop']);
    $routes->match(['GET', 'HEAD'], 'search', 'Shop::search', ['as' => 'search']);
    /*
     * /collection is the designed landing page; /collections stays as the plain
     * index it always was, so nothing that already links there breaks.
     */
    $routes->match(['GET', 'HEAD'], 'corporate', 'Pages::corporate');
    $routes->match(['GET', 'HEAD'], 'collection', 'Pages::collections');
    $routes->match(['GET', 'HEAD'], 'collections', 'Collections::index', ['as' => 'collections']);
    $routes->match(['GET', 'HEAD'], 'collections/(:segment)', 'Shop::collection/$1', ['as' => 'collection']);

    /*
     * The singular form, which is what gets typed and shared.
     *
     * An occasion used to live at the ROOT — /diwali-2026 — which put it in the
     * same namespace as every category and page, so a new occasion could
     * silently shadow one. Under /collection it cannot.
     */
    $routes->match(['GET', 'HEAD'], 'collection/(:segment)', 'Shop::collection/$1');
    // A variant has its own URL, so one colour can be linked to directly.
    // Declared FIRST: routes match in order, and the single-segment rule would
    // otherwise swallow the two-segment URL.
    //
    // Only the plain product route is named — two routes cannot share a name,
    // and route('product', $slug) must keep meaning the canonical page.
    $routes->match(['GET', 'HEAD'], 'product/(:segment)/(:segment)', 'Products::show/$1/$2');
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
    $routes->post('cart/add.json', 'Cart::addJson');
    $routes->match(['GET', 'HEAD'], 'cart/drawer', 'Cart::drawer');
    $routes->post('cart/update', 'Cart::update');
    $routes->post('cart/remove', 'Cart::remove');
    $routes->post('cart/coupon', 'Cart::applyCoupon');
    $routes->post('cart/coupon/remove', 'Cart::removeCoupon');

    $routes->match(['GET', 'HEAD'], 'checkout', 'Checkout::show', ['as' => 'checkout']);
    $routes->post('checkout', 'Checkout::place');
    $routes->match(['GET', 'HEAD'], 'order/(:segment)', 'Checkout::confirmation/$1', ['as' => 'order']);

    // ---- Customer accounts (Phase 5) ----
    // Public: this is how you get past the customerAuth filter.
    // ---- Sign in: one-time codes and Google, no passwords ----
    $routes->match(['GET', 'HEAD'], 'account/login', 'Auth::index');
    $routes->post('account/code/request', 'Auth::requestCode');
    $routes->match(['GET', 'HEAD'], 'account/code', 'Auth::codeForm');
    $routes->post('account/code/verify', 'Auth::verifyCode');
    $routes->post('account/code/resend', 'Auth::resend');
    $routes->post('account/register', 'Auth::register');
    $routes->match(['GET', 'HEAD'], 'account/register', 'Auth::index', ['as' => 'register']);
    $routes->match(['GET', 'HEAD'], 'account/google', 'Auth::google');
    $routes->match(['GET', 'HEAD'], 'account/google/callback', 'Auth::googleCallback');
    $routes->match(['GET', 'HEAD', 'POST'], 'account/finish', 'Auth::completeProfile');
    $routes->post('account/logout', 'Account::logout');

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

    $routes->match(['GET', 'HEAD'], 'account/orders', 'AccountArea::orders');
    $routes->match(['GET', 'HEAD'], 'account/orders/(:segment)', 'AccountArea::order/$1');

    $routes->match(['GET', 'HEAD'], 'account/addresses', 'AccountArea::addresses');
    $routes->post('account/addresses', 'AccountArea::saveAddress');
    $routes->post('account/addresses/delete', 'AccountArea::deleteAddress');
    $routes->post('account/addresses/default', 'AccountArea::makeDefaultAddress');

});

/*
 * The wishlist is PUBLIC.
 *
 * It used to sit inside the customerAuth group, which meant the heart on a
 * product card did nothing at all until you had registered — backwards, since
 * saving things is what a visitor does before deciding to make an account. A
 * guest's saves are keyed to a long-lived httpOnly cookie and adopted into the
 * account on sign-in.
 */
$routes->group('', ['namespace' => 'App\Controllers\Storefront'], static function ($routes) {
    $routes->match(['GET', 'HEAD'], 'wishlist', 'Wishlist::index');
    $routes->post('wishlist/toggle', 'Wishlist::toggle');
    $routes->post('wishlist/toggle.json', 'Wishlist::toggleJson');
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
    // (:segment) not (:any): the token is a single path element with no slashes.
    $routes->match(['GET', 'HEAD'], 'customers/(:segment)', 'Customers::show/$1', ['filter' => 'adminAuth:customers.view']);

    // ---- Banners: one page per slot ----
    $routes->match(['GET', 'HEAD'], 'banners', 'Banners::index', ['filter' => 'adminAuth:content.manage']);
    $routes->post('banners/bulk', 'Banners::bulk', ['filter' => 'adminAuth:content.manage']);
    $routes->post('banners', 'Banners::store', ['filter' => 'adminAuth:content.manage']);
    $routes->match(['GET', 'HEAD'], 'banners/(:num)/edit', 'Banners::edit/$1', ['filter' => 'adminAuth:content.manage']);
    $routes->post('banners/(:num)', 'Banners::update/$1', ['filter' => 'adminAuth:content.manage']);
    $routes->post('banners/(:num)/delete', 'Banners::delete/$1', ['filter' => 'adminAuth:content.manage']);
    // Slug routes come AFTER the numeric ones, or "banners/12/edit" would be
    // read as a slot named "12".
    $routes->match(['GET', 'HEAD'], 'banners/(:alphanum)/new', 'Banners::create/$1', ['filter' => 'adminAuth:content.manage']);
    $routes->match(['GET', 'HEAD'], 'banners/(:alphanum)', 'Banners::slot/$1', ['filter' => 'adminAuth:content.manage']);

    $routes->match(['GET', 'HEAD'], 'reports', 'Reports::index', ['filter' => 'adminAuth:reports.view']);
    $routes->match(['GET', 'HEAD'], 'reports/export/(:segment)', 'Reports::export/$1', ['filter' => 'adminAuth:reports.view']);

    $routes->match(['GET', 'HEAD'], 'roles', 'Roles::index', ['filter' => 'adminAuth:roles.manage']);
    $routes->match(['GET', 'HEAD'], 'roles/new', 'Roles::create', ['filter' => 'adminAuth:roles.manage']);
    $routes->post('roles', 'Roles::store', ['filter' => 'adminAuth:roles.manage']);
    $routes->match(['GET', 'HEAD'], 'roles/(:num)/edit', 'Roles::edit/$1', ['filter' => 'adminAuth:roles.manage']);
    $routes->post('roles/(:num)', 'Roles::update/$1', ['filter' => 'adminAuth:roles.manage']);
    $routes->post('roles/(:num)/delete', 'Roles::delete/$1', ['filter' => 'adminAuth:roles.manage']);

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

    // ---- Occasions ----
    $routes->match(['GET', 'HEAD'], 'occasions', 'Occasions::index', ['filter' => 'adminAuth:content.manage']);
    $routes->match(['GET', 'HEAD'], 'occasions/new', 'Occasions::create', ['filter' => 'adminAuth:content.manage']);
    $routes->post('occasions', 'Occasions::store', ['filter' => 'adminAuth:content.manage']);
    $routes->match(['GET', 'HEAD'], 'occasions/(:num)/edit', 'Occasions::edit/$1', ['filter' => 'adminAuth:content.manage']);
    $routes->post('occasions/(:num)', 'Occasions::update/$1', ['filter' => 'adminAuth:content.manage']);
    $routes->post('occasions/(:num)/delete', 'Occasions::delete/$1', ['filter' => 'adminAuth:content.manage']);

    // ---- Image alt text ----
    $routes->match(['GET', 'HEAD'], 'media', 'Media::index', ['filter' => 'adminAuth:content.manage']);
    $routes->post('media', 'Media::save', ['filter' => 'adminAuth:content.manage']);

    // ---- Homepage ----
    $routes->match(['GET', 'HEAD'], 'homepage', 'Homepage::index', ['filter' => 'adminAuth:homepage.manage']);
    $routes->post('homepage', 'Homepage::save', ['filter' => 'adminAuth:homepage.manage']);
    $routes->post('homepage/restore', 'Homepage::restore', ['filter' => 'adminAuth:homepage.manage']);
    $routes->match(['GET', 'HEAD'], 'homepage/testimonials/new', 'Homepage::testimonial', ['filter' => 'adminAuth:homepage.manage']);
    $routes->post('homepage/testimonials', 'Homepage::saveTestimonial', ['filter' => 'adminAuth:homepage.manage']);
    $routes->match(['GET', 'HEAD'], 'homepage/testimonials/(:num)', 'Homepage::testimonial/$1', ['filter' => 'adminAuth:homepage.manage']);
    $routes->post('homepage/testimonials/(:num)', 'Homepage::saveTestimonial/$1', ['filter' => 'adminAuth:homepage.manage']);
    $routes->post('homepage/testimonials/(:num)/delete', 'Homepage::deleteTestimonial/$1', ['filter' => 'adminAuth:homepage.manage']);

    // ---- Appearance ----
    $routes->match(['GET', 'HEAD'], 'appearance', 'Appearance::index');
    $routes->post('appearance', 'Appearance::save', ['filter' => 'adminAuth:settings.manage']);
    $routes->post('appearance/reset', 'Appearance::reset', ['filter' => 'adminAuth:settings.manage']);

    // ---- Shop identity ----
    $routes->match(['GET', 'HEAD'], 'brand', 'Brand::index');
    $routes->post('brand', 'Brand::save', ['filter' => 'adminAuth:settings.manage']);
    $routes->post('brand/restore', 'Brand::restore', ['filter' => 'adminAuth:settings.manage']);

    // ---- Mail configuration ----
    // ---- Header and footer, in one place ----
    $routes->match(['GET', 'HEAD'], 'chrome', 'Chrome::index', ['filter' => 'adminAuth:settings.manage']);
    $routes->post('chrome', 'Chrome::save', ['filter' => 'adminAuth:settings.manage']);

    // ---- Sign-in screen: its wording and the Google credentials ----
    // ---- Catalogue export, one row per variant ----
    $routes->match(['GET', 'HEAD'], 'catalogue/export', 'CatalogueExport::index', ['filter' => 'adminAuth:catalogue.manage']);
    $routes->match(['GET', 'HEAD'], 'catalogue/export.csv', 'CatalogueExport::csv', ['filter' => 'adminAuth:catalogue.manage']);
    $routes->match(['GET', 'HEAD'], 'catalogue/export.sql', 'CatalogueExport::sql', ['filter' => 'adminAuth:catalogue.manage']);

    // ---- Variants, per product ----
    $routes->match(['GET', 'HEAD'], 'products/(:num)/variants', 'Variants::index/$1', ['filter' => 'adminAuth:catalogue.manage']);
    $routes->post('products/(:num)/variants', 'Variants::save/$1', ['filter' => 'adminAuth:catalogue.manage']);
    $routes->post('products/(:num)/variants/new', 'Variants::create/$1', ['filter' => 'adminAuth:catalogue.manage']);
    $routes->post('products/(:num)/variants/(:num)/delete', 'Variants::delete/$1/$2', ['filter' => 'adminAuth:catalogue.manage']);

    // ---- Attributes: colour, size, shape, finish ----
    $routes->match(['GET', 'HEAD'], 'attributes', 'Attributes::index', ['filter' => 'adminAuth:catalogue.manage']);
    $routes->post('attributes/save', 'Attributes::save', ['filter' => 'adminAuth:catalogue.manage']);
    $routes->post('attributes/values', 'Attributes::saveValue', ['filter' => 'adminAuth:catalogue.manage']);
    $routes->post('attributes/values/(:num)/delete', 'Attributes::deleteValue/$1', ['filter' => 'adminAuth:catalogue.manage']);

    $routes->match(['GET', 'HEAD'], 'auth', 'AuthSettings::index', ['filter' => 'adminAuth:settings.manage']);
    $routes->post('auth', 'AuthSettings::save', ['filter' => 'adminAuth:settings.manage']);

    // ---- Mail queue: what has been sent, and what failed ----
    $routes->match(['GET', 'HEAD'], 'mail/queue', 'MailQueue::index', ['filter' => 'adminAuth:settings.manage']);
    $routes->post('mail/queue/drain', 'MailQueue::drain', ['filter' => 'adminAuth:settings.manage']);
    $routes->match(['GET', 'HEAD'], 'mail/queue/(:num)', 'MailQueue::show/$1', ['filter' => 'adminAuth:settings.manage']);
    $routes->post('mail/queue/(:num)/retry', 'MailQueue::retry/$1', ['filter' => 'adminAuth:settings.manage']);

    $routes->match(['GET', 'HEAD'], 'mail', 'MailSettings::index');
    $routes->post('mail', 'MailSettings::save', ['filter' => 'adminAuth:settings.manage']);
    $routes->post('mail/test', 'MailSettings::test', ['filter' => 'adminAuth:settings.manage']);
    $routes->post('mail/drain', 'MailSettings::drain', ['filter' => 'adminAuth:settings.manage']);

    // Google OAuth. The callback is a GET because Google redirects the browser
    // back to it; it is still behind the admin filter, and the `state` value is
    // what actually proves the round trip belongs to this administrator.
    $routes->post('mail/google/connect', 'MailSettings::googleConnect', ['filter' => 'adminAuth:settings.manage']);
    $routes->match(['GET', 'HEAD'], 'mail/google/callback', 'MailSettings::googleCallback', ['filter' => 'adminAuth:settings.manage']);
    $routes->post('mail/google/disconnect', 'MailSettings::googleDisconnect', ['filter' => 'adminAuth:settings.manage']);

    // ---- Settings ----
    $routes->match(['GET', 'HEAD'], 'settings', 'Settings::index');
    $routes->post('settings', 'Settings::update', ['filter' => 'adminAuth:settings.manage']);
    // The master switch names its own permission, separate from settings.manage.
    $routes->post('settings/journey', 'Settings::switchJourney', ['filter' => 'adminAuth:settings.journey_mode']);

    // ---- Audit ----
    $routes->match(['GET', 'HEAD'], 'audit', 'Audit::index', ['filter' => 'adminAuth:audit.view']);
});

// =====================================================================
// CATEGORY URLS AT THE SITE ROOT
//
// /teas-infusions and /gifting/teas-infusions/green both resolve here.
//
// THIS BLOCK MUST STAY LAST IN THE FILE. CodeIgniter matches routes in the
// order they are defined, so a catch-all declared earlier would swallow /cart,
// /checkout, /admin and everything else. Registering it after every real route
// means those all win first, and only an unclaimed path reaches the category
// resolver — which 404s when the path is not a category.
//
// The second line of defence is in the admin: a TOP-LEVEL category slug is
// checked against the registered routes before it can be saved, so a category
// can never be created that would shadow a real page.
//
// Depth is bounded by CategoryModel::MAX_DEPTH, and each segment is
// (:segment), which excludes slashes — so these patterns cannot run away.
// =====================================================================
$routes->group('', ['namespace' => 'App\Controllers\Storefront'], static function (RouteCollection $routes): void {
    $routes->match(['GET', 'HEAD'], '(:segment)/(:segment)/(:segment)/(:segment)/(:segment)', 'Shop::path/$1/$2/$3/$4/$5');
    $routes->match(['GET', 'HEAD'], '(:segment)/(:segment)/(:segment)/(:segment)', 'Shop::path/$1/$2/$3/$4');
    $routes->match(['GET', 'HEAD'], '(:segment)/(:segment)/(:segment)', 'Shop::path/$1/$2/$3');
    $routes->match(['GET', 'HEAD'], '(:segment)/(:segment)', 'Shop::path/$1/$2');
    $routes->match(['GET', 'HEAD'], '(:segment)', 'Shop::path/$1');
});

