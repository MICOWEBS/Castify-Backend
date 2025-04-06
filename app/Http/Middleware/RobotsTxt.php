<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RobotsTxt
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
        if ($request->path() === 'robots.txt') {
            $content = "User-agent: *\n";
            
            // Determine if we're in production
            if (app()->environment('production')) {
                // Allow most pages
                $content .= "Allow: /\n";
                
                // Disallow sensitive areas
                $content .= "Disallow: /admin\n";
                $content .= "Disallow: /api/*\n";
                $content .= "Disallow: /dashboard\n";
                $content .= "Disallow: /profile\n";
                $content .= "Disallow: /login\n";
                $content .= "Disallow: /register\n";
                $content .= "Disallow: /password/*\n";
                
                // Add sitemap
                $content .= "Sitemap: " . url('/sitemap.xml') . "\n";
            } else {
                // Disallow everything in non-production environments
                $content .= "Disallow: /\n";
            }
            
            return response($content, 200)
                ->header('Content-Type', 'text/plain');
        }

        return $next($request);
    }
} 