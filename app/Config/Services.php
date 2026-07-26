<?php

declare(strict_types=1);

namespace Config;

use App\Models\SettingModel;
use App\Services\AuditService;
use App\Services\CartService;
use App\Services\GiftBoxBuilderService;
use App\Services\HtmlSanitiser;
use App\Services\ImageUploadService;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Services\SettingsService;
use CodeIgniter\Config\BaseService;

/**
 * Application services.
 *
 * Register shared, long-lived collaborators here so controllers and views
 * resolve the same instance instead of newing one up each time.
 */
class Services extends BaseService
{
    /** Runtime admin settings, including the Buy/Enquire master switch. */
    public static function settings(bool $getShared = true): SettingsService
    {
        if ($getShared) {
            return static::getSharedInstance('settings');
        }

        return new SettingsService(model(SettingModel::class));
    }

    /** Recomputes every total from the database. Never trusts the client. */
    public static function pricing(bool $getShared = true): PricingService
    {
        if ($getShared) {
            return static::getSharedInstance('pricing');
        }

        return new PricingService(static::settings());
    }

    /** Owns the database-backed cart. */
    public static function cart(bool $getShared = true): CartService
    {
        if ($getShared) {
            return static::getSharedInstance('cart');
        }

        return new CartService(static::settings(), static::pricing());
    }

    /** The Build-Your-Own-Gift-Box flow. */
    public static function builder(bool $getShared = true): GiftBoxBuilderService
    {
        if ($getShared) {
            return static::getSharedInstance('builder');
        }

        return new GiftBoxBuilderService(static::cart());
    }

    /** Turns a cart into an order, transactionally. */
    public static function orders(bool $getShared = true): OrderService
    {
        if ($getShared) {
            return static::getSharedInstance('orders');
        }

        return new OrderService(static::settings(), static::pricing(), static::cart());
    }

    /** Allowlist HTML sanitiser for staff-authored content. */
    public static function sanitiser(bool $getShared = true): HtmlSanitiser
    {
        if ($getShared) {
            return static::getSharedInstance('sanitiser');
        }

        return new HtmlSanitiser();
    }

    /** Validates, re-encodes and stores uploaded images. */
    public static function images(bool $getShared = true): ImageUploadService
    {
        if ($getShared) {
            return static::getSharedInstance('images');
        }

        return new ImageUploadService();
    }

    /** Writes the admin audit trail. */
    public static function audit(bool $getShared = true): AuditService
    {
        if ($getShared) {
            return static::getSharedInstance('audit');
        }

        return new AuditService();
    }
}
