<?php

declare(strict_types=1);

namespace Config;

use App\Filters\AdminAuthFilter;
use App\Filters\CustomerAuthFilter;
use App\Filters\SecurityHeadersFilter;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    /**
     * @var array<string, class-string|list<class-string>>
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,

        // Rasmein
        'headers'       => SecurityHeadersFilter::class,
        'adminAuth'     => AdminAuthFilter::class,
        'customerAuth'  => CustomerAuthFilter::class,
    ];

    /**
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            'forcehttps',
            'pagecache',
        ],
        'after' => [
            'pagecache',
            'performance',
            // The debug toolbar must never load in production. CI4 already
            // guards this, but the guard is re-asserted in the constructor.
            'toolbar',
        ],
    ];

    /**
     * @var array{
     *     before: array<string, array{except: list<string>|string}>|list<string>,
     *     after: array<string, array{except: list<string>|string}>|list<string>
     * }
     */
    public array $globals = [
        'before' => [
            // Rejects control characters / invalid UTF-8 in any input.
            'invalidchars',
        ],
        'after' => [
            'headers',
        ],
    ];

    /**
     * CSRF is applied by HTTP method so every state-changing request is
     * covered, including any route added later that someone forgets to list.
     *
     * @var array<string, list<string>>
     */
    public array $methods = [
        'POST'   => ['csrf'],
        'PUT'    => ['csrf'],
        'PATCH'  => ['csrf'],
        'DELETE' => ['csrf'],
    ];

    /**
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [];

    public function __construct()
    {
        parent::__construct();

        // Belt and braces: drop the toolbar entirely outside development.
        if (ENVIRONMENT !== 'development') {
            $this->required['after'] = array_values(
                array_filter($this->required['after'], static fn ($f): bool => $f !== 'toolbar')
            );
        }
    }
}
