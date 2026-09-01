<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(18));
        $request->attributes->set('csp_nonce', $nonce);
        $response = $next($request);
        $dev = app()->environment('local');
        $captcha = config('image-tools.captcha.enabled') ? ' https://challenges.cloudflare.com' : '';
        $analyticsUrl = filter_var(config('site.analytics_script_url'), FILTER_VALIDATE_URL);
        $analyticsOrigin = $analyticsUrl && in_array(parse_url($analyticsUrl, PHP_URL_SCHEME), ['http', 'https'], true)
            ? ' '.parse_url($analyticsUrl, PHP_URL_SCHEME).'://'.parse_url($analyticsUrl, PHP_URL_HOST)
            : '';
        $script = "'self' 'nonce-$nonce'".($dev ? ' http://localhost:5173 http://127.0.0.1:5173' : '').$captcha.$analyticsOrigin;
        $connect = "'self'".($dev ? ' ws://localhost:5173 ws://127.0.0.1:5173 http://localhost:5173 http://127.0.0.1:5173' : '').$analyticsOrigin;
        $frame = config('image-tools.captcha.enabled') ? ' https://challenges.cloudflare.com' : '';
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src $script; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src $connect$captcha; frame-src 'self'$frame; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; upgrade-insecure-requests");
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure() && ! $dev) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
