<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\StorefrontSeo;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SitemapController extends Controller
{
    // Conservative size also leaves ample room below the 50 MB XML limit.
    public const PAGE_SIZE = 10000;

    public function index(StorefrontSeo $seo): StreamedResponse
    {
        $counts = ['products' => $this->query('products')->count(), 'categories' => $this->query('categories')->count()];
        if (array_sum($counts) + count(StorefrontSeo::STATIC_ROUTES) <= self::PAGE_SIZE) {
            return $this->xml('urlset', function () use ($seo): void {
                $this->staticUrls($seo);
                foreach (['products', 'categories'] as $kind) {
                    foreach ($this->query($kind)->lazyById(500) as $model) {
                        $this->entry('url', $seo->url($this->routeName($kind), [$model->slug]));
                    }
                }
            });
        }

        return $this->xml('sitemapindex', function () use ($seo, $counts): void {
            $this->entry('sitemap', $seo->url('sitemap.page', ['kind' => 'pages', 'page' => 1]));
            foreach ($counts as $kind => $count) {
                for ($page = 1; $page <= (int) ceil($count / self::PAGE_SIZE); $page++) {
                    $this->entry('sitemap', $seo->url('sitemap.page', compact('kind', 'page')));
                }
            }
        });
    }

    public function page(StorefrontSeo $seo, string $kind, int $page): StreamedResponse
    {
        if ($kind === 'pages') {
            abort_unless($page === 1, 404);
            return $this->xml('urlset', fn () => $this->staticUrls($seo));
        }

        $query = $this->query($kind)->orderBy('id')->forPage($page, self::PAGE_SIZE);
        abort_unless((clone $query)->first() !== null, 404);

        return $this->xml('urlset', function () use ($seo, $kind, $query): void {
            foreach ($query->cursor() as $model) {
                $this->entry('url', $seo->url($this->routeName($kind), [$model->slug]));
            }
        });
    }

    private function query(string $kind): Builder
    {
        // is_active is the existing publication flag; no stock-based exclusion.
        return ($kind === 'products' ? Product::query() : Category::query())
            ->where('is_active', true)->select(['id', 'slug']);
    }

    private function routeName(string $kind): string
    {
        return $kind === 'products' ? 'products.show' : 'categories.show';
    }

    private function staticUrls(StorefrontSeo $seo): void
    {
        foreach (StorefrontSeo::STATIC_ROUTES as $route) {
            $this->entry('url', $seo->url($route));
        }
    }

    private function entry(string $tag, string $url): void
    {
        echo '<'.$tag.'><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></'.$tag.'>';
    }

    private function xml(string $root, callable $entries): StreamedResponse
    {
        // No cache: publication changes are reflected on the next request.
        // lastmod is omitted rather than inferring changes to assembled pages.
        return response()->stream(function () use ($root, $entries): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<'.$root.' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            $entries();
            echo '</'.$root.'>';
        }, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'no-cache, public']);
    }
}
