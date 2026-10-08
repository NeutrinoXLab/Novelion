<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StorefrontSeo
{
    // Keep canonical URLs independent of request hosts and tracking parameters.
    public const ORIGIN = 'https://novelions.ro';

    public const STATIC_ROUTES = [
        'home', 'products.index', 'products.new', 'products.promotions', 'categories.index',
        'pages.about', 'pages.contact', 'pages.shipping', 'pages.returns', 'pages.terms', 'pages.privacy',
    ];

    public function isPublic(Request $request): bool
    {
        return $request->routeIs(...[...self::STATIC_ROUTES, 'products.show', 'categories.show']);
    }

    public function url(string $route, array $parameters = []): string
    {
        return self::ORIGIN.route($route, $parameters, false);
    }

    public function metadata(Request $request): array
    {
        if (! $this->isPublic($request)) {
            return ['canonical' => null, 'title' => null, 'description' => null];
        }

        $route = $request->route();
        $parameters = $route->parameters();
        // Pagination represents distinct listing content; campaign parameters do not.
        if ($request->routeIs('products.index', 'products.new', 'products.promotions', 'categories.show')) {
            $page = filter_var($request->query('page'), FILTER_VALIDATE_INT);
            if ($page !== false && $page > 1) {
                $parameters['page'] = $page;
            }
        }

        $model = $route->parameter('product') ?? $route->parameter('category');
        $title = null;
        $description = null;
        if ($model instanceof Product || $model instanceof Category) {
            $title = trim((string) $model->seo_title) ?: null;
            $description = trim((string) $model->seo_description);
            if ($description === '') {
                $description = $model instanceof Product ? $model->short_description : $model->description;
            }
            $description = Str::limit(Str::squish(strip_tags((string) $description)), 160);
        }

        return ['canonical' => $this->url($route->getName(), $parameters),
            'title' => $title, 'description' => $description];
    }
}
