<?php

use App\Http\Middleware\TrackVisits;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->append(\App\Http\Middleware\SearchIndexing::class);
        $middleware->web(append: [TrackVisits::class]);

        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_PROTO);
        $middleware->trustHosts(at: ['^novelions\.ro$', '^www\.novelions\.ro$'], subdomains: false);

        $middleware->validateCsrfTokens(
            except: [
                'stripe/webhook',
            ],
        );

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->create();
