<?php

namespace App\Providers;

use App\Http\Middleware\CheckAdminRole;
use App\Http\Middleware\EnsureEmailIsVerified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Define rate limiting for general API
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(env('APP_ENV') === 'production' ? 30 : 60)
                ->by($request->user()?->id ?: $request->ip());
        });

        // More strict rate limit for authentication attempts
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(env('APP_ENV') === 'production' ? 3 : 5)
                ->by($request->ip());
        });

        // Rate limit for uploads
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perHour(env('APP_ENV') === 'production' ? 10 : 20)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Register middleware aliases
        $router = $this->app['router'];
        $router->aliasMiddleware('admin', CheckAdminRole::class);
        $router->aliasMiddleware('verified.email', EnsureEmailIsVerified::class);
        $router->aliasMiddleware('throttle.auth', 'throttle:auth');
        $router->aliasMiddleware('throttle.uploads', 'throttle:uploads');

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
