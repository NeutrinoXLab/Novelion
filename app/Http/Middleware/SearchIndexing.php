<?php

namespace App\Http\Middleware;

use App\Services\StorefrontSeo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchIndexing
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->isMethodSafe()
            && (str_contains((string) $response->headers->get('Content-Type'), 'text/html') || $request->routeIs('products.search'))
            && (! app(StorefrontSeo::class)->isPublic($request) || $response->getStatusCode() >= 400)) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        return $response;
    }
}
