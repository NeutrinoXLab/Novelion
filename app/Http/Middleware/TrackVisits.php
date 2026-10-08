<?php

namespace App\Http\Middleware;

use App\Services\VisitorAnalytics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackVisits
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // Explicit storefront route allowlist excludes Admin, assets, health,
        // account/payment callbacks and automated endpoints by construction.
        $routes = ['home', 'products.index', 'products.new', 'products.promotions', 'products.show',
            'categories.index', 'categories.show', 'cart.index', 'pages.about', 'pages.contact',
            'pages.shipping', 'pages.returns', 'pages.terms', 'pages.privacy'];
        $agent = (string) $request->userAgent();
        if (! $request->isMethod('GET') || ! $request->routeIs(...$routes) || $request->ajax()
            || $response->getStatusCode() !== 200
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            || $agent === '' || preg_match('/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|headless|lighthouse|curl|wget|python|monitor|uptime|pingdom|preview/i', $agent)) {
            return $response;
        }
        try {
            // Laravel's trusted-proxy policy is the sole source of client IPs.
            app(VisitorAnalytics::class)->record((string) $request->ip());
        } catch (Throwable $exception) {
            // Analytics failure must never break the storefront response.
            report($exception);
        }

        return $response;
    }
}
