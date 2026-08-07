<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SecurityHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // No action required before request
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Security Headers for Production Hardening & Global CSRF Token Sync
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-XSS-Protection', '1; mode=block');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        $response->setHeader('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=()');
        $response->setHeader('Content-Security-Policy', "default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';");

        // Selective Injection for AJAX and JSON Responses
        if ($request->isAJAX() || 
            str_contains($response->getHeaderLine('Content-Type'), 'application/json') || 
            $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $response->setHeader('X-CSRF-TOKEN', csrf_hash());
        }

        return $response;
    }
}
