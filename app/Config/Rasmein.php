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
    /**
     * Identity assets and details set from the admin panel.
     *
     * Populated by Services::brand(); empty when read straight from config.
     * A plain map rather than typed properties because these have no sensible
     * PHP default — an unset logo is genuinely absent, not "the default logo".
     *
     * @var array<string, string>
     */
    /**
     * Where a banner can appear.
     *
     * One list, because it previously lived in three: the database enum, the
     * model's in_list rule, and the admin dropdown. Adding a slot to two of
     * them left it uncreatable from the panel.
     *
     * @var array<string, string>
     */
    public array $bannerPositions = [
        'home_hero'     => 'Homepage — hero slide',
        'home_strip'    => 'Homepage — strip',
        'home_feature'  => 'Homepage — feature band',
        'home_client'   => 'Homepage — client logo',
        'home_gallery'  => 'Homepage — gallery',
        'category_top'  => 'Category page — top',
        'gift_builder'  => 'Gift box builder',
    ];

    /**
     * Price bands for the shop filter: [from, to (exclusive) or null, label].
     *
     * Fixed bands rather than a slider: a slider on a catalogue with a long
     * tail is unusable, and a band can carry a count so the person knows what
     * they will get before clicking.
     *
     * @var array<int, array{0: float, 1: float|null, 2: string}>
     */
    public array $priceBands = [
        [0, 2000, 'Under 2,000'],
        [2000, 5000, '2,000 — 5,000'],
        [5000, 12000, '5,000 — 12,000'],
        [12000, 25000, '12,000 — 25,000'],
        [25000, null, 'Above 25,000'],
    ];

    public array $identity = [];

    /** @var array<string, string> Social links that are actually set. */
    public array $social = [];

    public string $brandName    = 'Rasmein';
    public string $brandTagline = 'Gifting that carries a feeling.';
    public string $supportEmail = 'hello@rasmein.com';
    public string $supportPhone = '+91 98765 43210';

    /*
     * Digits only, with the country code — wa.me takes nothing else.
     *
     * A first-class property rather than a key inside $identity: the footer,
     * the homepage and every lead form need it, and reaching into an array for
     * something that important is how one of them ends up reading a key that
     * was never set.
     */
    public string $whatsapp = '';

    // ------------------------------------------------------------- currency
    public string $currency       = 'INR';
    public string $currencySymbol = '₹';

    /**
     * The two site journeys. The active one is an admin setting
     * (`journey_mode`) resolved server-side on every order-creating request.
     */
    /** Where a visitor's chosen journey is remembered. */
    public const MODE_COOKIE  = 'rs_mode';

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
    /**
     * Upload size cap.
     *
     * Was 2 MB, which rejected the very photographs a shop should be uploading —
     * a phone camera produces 3–8 MB and a DSLR far more. That mattered less
     * when the original was served as-is; now that every upload is downscaled
     * into a size ladder, a large original costs nothing at serving time and
     * gives the ladder something to work from.
     *
     * Must stay below PHP's own upload_max_filesize and post_max_size, or the
     * request is discarded before the application ever sees it.
     */
    public int   $maxImageBytes     = 12_582_912; // 12 MB
    public int   $maxImageWidth     = 2400;

    /**
     * Widths generated for every upload, for responsive `srcset`.
     *
     * WHY SEVERAL SIZES RATHER THAN ONE BIG ONE
     *
     * A card 400 CSS pixels wide on a 2x phone needs 800 real pixels. Serving a
     * single 2400px file makes that card sharp but costs the visitor five times
     * the bytes; serving a single 800px file is fast but soft on a large
     * monitor. Generating a ladder and letting the browser pick is the only way
     * to have both.
     *
     * A variant is NEVER larger than the original — upscaling invents pixels
     * and looks worse than letting the browser stretch.
     *
     * @var list<int>
     */
    public array $imageWidths = [320, 480, 768, 1024, 1440, 1920];

    /**
     * JPEG/WebP quality.
     *
     * 82 for WebP is visually equivalent to about 88 for JPEG at roughly 70% of
     * the size, which is why WebP is generated for every upload and offered
     * first.
     */
    public int   $jpegQuality       = 88;
    public int   $webpQuality       = 82;

    /**
     * Sharpening applied after downscaling, 0 to disable.
     *
     * Every resample softens edges — that is arithmetic, not a bug. A mild
     * unsharp mask afterwards restores the crispness the resize removed. It does
     * NOT invent detail: an image that was blurry when uploaded is still blurry,
     * just with more contrast at the edges it does have.
     */
    public float $sharpenAmount     = 0.6;

    /**
     * Width caps for identity images, by setting key.
     *
     * A logo displayed at 40px tall does not need 2400 pixels of width, and a
     * favicon needs fewer still. The sharing image is the exception: 1200×630 is
     * what WhatsApp and the social platforms expect.
     *
     * @var array<string, int>
     */
    public array $brandImageWidths = [
        'brand_logo'       => 600,
        'brand_logo_light' => 600,
        'brand_favicon'    => 512,
        'brand_og_image'   => 1200,
    ];

    public array $uploadPaths = [
        'products' => 'uploads/products',
        'boxes'    => 'uploads/boxes',
        'banners'  => 'uploads/banners',
        // Images inserted from inside the rich text editor.
        'content'  => 'uploads/content',
        'brand'    => 'uploads/brand',
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
