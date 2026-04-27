<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

if (!defined('TELESCOPE_TOKEN_KEY')) {
    define('TELESCOPE_TOKEN_KEY', 'telescope_token');
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: [
            'appearance',
            'sidebar_state'
        ]);

        $middleware->statefulApi();
        $middleware->trustProxies(at: '*');

        $middleware->trustHosts(at: [
            'shopify-laravel.oseinfosites.com',
            'notably-modern-alien.ngrok-free.app'
        ]);

        $middleware->preventRequestForgery();

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class
        ]);

        $middleware->api(remove: \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);
        $middleware->removeFromGroup('telescope', "\\Laravel\Sentinel\\Http\\Middleware\\SentinelMiddleware:telescope");



        // $middleware->priority([
        //     "Illuminate\\Cookie\\Middleware\\EncryptCookies",
        //     "Illuminate\\Cookie\\Middleware\\AddQueuedCookiesToResponse",
        //     "Illuminate\\Session\\Middleware\\StartSession",
        //     "Illuminate\\View\\Middleware\\ShareErrorsFromSession",
        //     "Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery",
        //     "Laravel\\Sentinel\\Http\\Middleware\\SentinelMiddleware:telescope",
        //     "Laravel\\Telescope\\Http\\Middleware\\Authorize"
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
