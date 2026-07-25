<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Project-wide constants for the Rasmein platform.
 *
 * Anything here is a *code* concern (enum vocabularies, upload limits,
 * page sizes). Anything an admin can change at runtime lives in the
 * `settings` table and is read through \App\Services\SettingsService.
 */
class Rasmein extends BaseConfig
{
    // ---------------------------------------------------------------- brand
    public string $brandName    = 'Rasmein';
    public string $brandTagline = 'Gifting that carries a feeling.';
    public string $supportEmail = 'hello@rasmein.com';
    public string $supportPhone = '+91 98765 43210';

    // ------------------------------------------------------------- currency
    public string $currency       = 'INR';
    public string $currencySymbol = '₹';

    /**
     * The two site journeys. The active one is an admin setting
     * (`journey_mode`) resolved server-side on every order-creating request.
     */
    public const MODE_BUY     = 'buy_now';
    public const MODE_ENQUIRE = 'enquire_now';

    /** A product may follow the site setting, or be pinned to one journey. */
    public const PRODUCT_MODE_INHERIT = 'inherit';

    public array $journeyModes = [
        self::MODE_BUY     => 'Buy now',
        self::MODE_ENQUIRE => 'Enquire now',
    ];

    // --------------------------------------------------------------- orders
    public array $orderStatuses = [
        'pending'    => 'Pending',
        'confirmed'  => 'Confirmed',
        'processing' => 'Processing',
        'packed'     => 'Packed',
        'dispatched' => 'Dispatched',
        'delivered'  => 'Delivered',
        'cancelled'  => 'Cancelled',
        'refunded'   => 'Refunded',
    ];

    public array $paymentStatuses = [
        'not_applicable' => 'Not applicable',
        'unpaid'         => 'Unpaid',
        'pending'        => 'Pending',
        'paid'           => 'Paid',
        'failed'         => 'Failed',
        'refunded'       => 'Refunded',
    ];

    public array $enquiryStatuses = [
        'new'       => 'New',
        'contacted' => 'Contacted',
        'quoted'    => 'Quoted',
        'won'       => 'Won',
        'lost'      => 'Lost',
        'spam'      => 'Spam',
    ];

    /** Order reference prefix — public-facing, paired with a UUID. */
    public string $orderRefPrefix   = 'RSM';
    public string $enquiryRefPrefix = 'ENQ';

    // -------------------------------------------------------------- uploads
    /** Whitelisted image MIME types. Extension alone is never trusted. */
    public array $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp'];
    public array $allowedImageExts  = ['jpg', 'jpeg', 'png', 'webp'];
    public int   $maxImageBytes     = 2_097_152; // 2 MB
    public int   $maxImageWidth     = 2400;

    public array $uploadPaths = [
        'products' => 'uploads/products',
        'boxes'    => 'uploads/boxes',
        'banners'  => 'uploads/banners',
    ];

    // ----------------------------------------------------------- pagination
    public int $storefrontPerPage = 12;
    public int $adminPerPage      = 20;

    // ------------------------------------------------------------ throttles
    /** Login attempts allowed per minute, per IP + identifier. */
    public int $loginAttemptsPerMinute = 5;
    /** Enquiry/lead submissions allowed per hour, per IP. */
    public int $enquiriesPerHour = 10;

    // ------------------------------------------------------------- gift box
    /** Hard ceiling on compartments, whatever an admin types. */
    public int $maxBoxCapacity = 24;
}
