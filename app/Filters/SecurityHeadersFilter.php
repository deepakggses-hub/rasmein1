<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Adds hardening response headers and strips the ones that advertise the
 * stack. Runs globally (after), so every response is covered — including
 * error pages and JSON.
 */
class SecurityHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->setHeader(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(self), usb=()'
        );

        // Do not let the platform announce itself. X-Powered-By is emitted by
        // PHP itself (expose_php), below the framework's response object, so it
        // has to be dropped at the SAPI level as well.
        $response->removeHeader('X-Powered-By');
        $response->removeHeader('Server');

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        // HSTS only once TLS is actually terminating in front of the app —
        // sending it over plain HTTP is meaningless and sending it too early
        // locks out a domain that is not yet fully on HTTPS.
        if (ENVIRONMENT === 'production' && $request->isSecure()) {
            $response->setHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
