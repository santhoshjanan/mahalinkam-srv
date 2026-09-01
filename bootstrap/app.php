<?php

use App\Http\Middleware\EnsureSignupsEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'signups.enabled' => EnsureSignupsEnabled::class,
        ]);

        // The production stack (compose.prod.yaml) has no bundled edge proxy —
        // the operator runs their own TLS-terminating proxy in front of port
        // 8080. Without trusting its X-Forwarded-* headers, Request::isSecure()
        // is false behind HTTPS termination, so Laravel emits http:// absolute
        // URLs and refuses to set secure cookies. TRUSTED_PROXIES defaults to
        // '*' (trust any peer) because the container's only ingress is that
        // proxy; set it to a comma-separated IP/CIDR list to be strict.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*') === '*'
                ? '*'
                : explode(',', (string) env('TRUSTED_PROXIES')),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
