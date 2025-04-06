<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecureHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // In production, add security headers
        if (app()->environment('production')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-XSS-Protection', '1; mode=block');
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
            
            // Configure Content Security Policy
            $cspDirectives = [
                "default-src" => "'self'",
                "script-src" => "'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
                "style-src" => "'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
                "font-src" => "'self' https://fonts.gstatic.com",
                "img-src" => "'self' data: https://res.cloudinary.com",
                "media-src" => "'self' https://res.cloudinary.com",
                "connect-src" => "'self' https://api.cloudinary.com",
                "frame-src" => "'self'"
            ];
            
            $csp = '';
            foreach ($cspDirectives as $directive => $value) {
                $csp .= "$directive $value; ";
            }
            
            $response->headers->set('Content-Security-Policy', trim($csp));
        }

        return $response;
    }
} 