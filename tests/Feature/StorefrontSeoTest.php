<?php

namespace Tests\Feature;

use App\Http\Controllers\SitemapController;
use App\Models\Category;
use App\Models\Product;
use App\Services\StorefrontSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontSeoTest extends TestCase
{
    use RefreshDatabase;

    private function product(Category $category, array $attributes = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $category->id, 'name' => 'Produs SEO', 'sku' => 'SEO',
            'purchase_price' => 10, 'selling_price' => 20, 'stock_quantity' => 0, 'is_active' => true,
        ], $attributes));
    }

    private function xml(string $path = '/sitemap.xml'): \SimpleXMLElement
    {
        $response = $this->get($path)->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->streamedContent());
        $this->assertNotFalse($xml);
        $this->assertSame(['' => 'http://www.sitemaps.org/schemas/sitemap/0.9'], $xml->getDocNamespaces());
        return $xml;
    }

    public function test_sitemap_contains_only_public_canonical_urls_and_updates_without_cache(): void
    {
        $category = Category::create(['name' => 'Public', 'slug' => 'casa-si-cadouri', 'is_active' => true]);
        $hidden = Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        $product = $this->product($category);
        $inactive = $this->product($category, ['name' => 'Inactiv', 'sku' => 'OFF', 'is_active' => false]);
        $deleted = $this->product($category, ['name' => 'Șters', 'sku' => 'DEL']);
        $deleted->delete();
        $seo = app(StorefrontSeo::class);
        $xml = $this->xml();
        $this->assertSame('urlset', $xml->getName());
        $urls = array_map('strval', $xml->xpath('//*[local-name()="loc"]'));
        $expected = array_map(fn ($route) => $seo->url($route), StorefrontSeo::STATIC_ROUTES);
        $expected[] = $seo->url('products.show', [$product->slug]);
        $expected[] = $seo->url('categories.show', [$category->slug]);
        $this->assertEqualsCanonicalizing($expected, $urls);
        foreach ($urls as $url) {
            $this->assertStringStartsWith('https://novelions.ro/', $url);
            $this->assertNull(parse_url($url, PHP_URL_QUERY));
        }
        foreach ([$inactive, $deleted] as $excluded) {
            $this->assertNotContains($seo->url('products.show', [$excluded->slug]), $urls);
        }
        $this->assertNotContains($seo->url('categories.show', [$hidden->slug]), $urls);
        $this->assertCount(0, $xml->xpath('//*[local-name()="lastmod" or local-name()="priority" or local-name()="changefreq"]'));

        $product->update(['is_active' => false]);
        $next = array_map('strval', $this->xml()->xpath('//*[local-name()="loc"]'));
        $this->assertNotContains($seo->url('products.show', [$product->slug]), $next);
    }

    public function test_inactive_and_missing_detail_pages_return_404(): void
    {
        $category = Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        $product = $this->product($category, ['is_active' => false]);
        $this->get(route('products.show', $product))->assertNotFound();
        $this->get(route('categories.show', $category))->assertNotFound();
        $this->get('/produs/does-not-exist')->assertNotFound();
        $this->get('/categorie/does-not-exist')->assertNotFound();
    }

    public function test_public_pages_have_canonical_urls_and_no_noindex(): void
    {
        $category = Category::create(['name' => 'Public', 'slug' => 'public', 'is_active' => true]);
        $product = $this->product($category);
        $paths = ['/', '/produse', '/categorie/public', '/produs/'.$product->slug, '/despre-noi'];
        foreach ($paths as $path) {
            $this->get($path.'?utm_source=test')->assertOk()
                ->assertHeaderMissing('X-Robots-Tag')
                ->assertSee('<link rel="canonical" href="https://novelions.ro'.$path.'">', false)
                ->assertDontSee('noindex', false);
        }
    }

    public function test_seo_fields_are_used_and_escaped_without_changing_product_content(): void
    {
        $category = Category::create(['name' => 'Public', 'slug' => 'public', 'is_active' => true,
            'seo_title' => 'Categorie SEO', 'seo_description' => 'Descriere categorie SEO']);
        $product = $this->product($category, ['seo_title' => 'Titlu <SEO>',
            'seo_description' => 'Descriere "SEO" & detalii', 'short_description' => 'Text comercial existent']);
        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('<title>Titlu &lt;SEO&gt;</title>', false)
            ->assertSee('content="Descriere &quot;SEO&quot; &amp; detalii"', false)
            ->assertSeeText('Text comercial existent');
        $this->get(route('categories.show', $category))->assertOk()
            ->assertSee('<title>Categorie SEO</title>', false)
            ->assertSee('content="Descriere categorie SEO"', false);
    }

    public function test_pagination_has_its_own_canonical_without_tracking_parameters(): void
    {
        $this->get('/produse?page=2&utm_source=test')->assertOk()
            ->assertSee('href="https://novelions.ro/produse?page=2"', false);
        foreach (['1', '0', '-2', 'bad', '%3Cscript%3E'] as $page) {
            $this->get('/produse?page='.$page)->assertOk()
                ->assertSee('<link rel="canonical" href="https://novelions.ro/produse">', false);
        }
    }

    public function test_private_html_and_search_json_are_noindex_without_public_canonical(): void
    {
        foreach (['/login', '/register', '/cart', '/search?q=test', '/admin/login'] as $path) {
            $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow')
                ->assertDontSee('rel="canonical"', false);
        }
    }

    public function test_canonical_host_does_not_follow_www_request_host(): void
    {
        $this->get('https://www.novelions.ro/produse?utm_source=test')->assertOk()
            ->assertSee('<link rel="canonical" href="https://novelions.ro/produse">', false);
    }

    public function test_product_part_respects_publication_and_independent_category_state(): void
    {
        $category = Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        $product = $this->product($category);
        $this->product($category, ['sku' => 'OFF', 'is_active' => false]);
        $xml = $this->xml('/sitemap-products-1.xml');
        $this->assertCount(1, $xml->url);
        $this->assertSame(app(StorefrontSeo::class)->url('products.show', [$product->slug]), (string) $xml->url[0]->loc);
        $this->get(route('products.show', $product))->assertOk();
    }

    public function test_robots_static_file_advertises_sitemap_without_blocking_assets(): void
    {
        // The web server serves this public file directly; HTTP is checked after deploy.
        $this->assertSame("User-agent: *\nDisallow:\n\nSitemap: https://novelions.ro/sitemap.xml\n", file_get_contents(public_path('robots.txt')));
    }

    public function test_xml_escapes_legacy_category_slugs(): void
    {
        $category = Category::create(['name' => 'Legacy', 'slug' => 'casa&cadouri', 'is_active' => true]);
        $urls = array_map('strval', $this->xml()->xpath('//*[local-name()="loc"]'));
        $this->assertContains(app(StorefrontSeo::class)->url('categories.show', [$category->slug]), $urls);
    }

    public function test_large_catalog_uses_bounded_sitemap_parts_without_missing_urls(): void
    {
        for ($start = 1; $start <= SitemapController::PAGE_SIZE + 1; $start += 500) {
            $rows = [];
            for ($id = $start; $id < min($start + 500, SitemapController::PAGE_SIZE + 2); $id++) {
                $rows[] = ['name' => 'Category '.$id, 'slug' => 'category-'.$id, 'is_active' => true];
            }
            DB::table('categories')->insert($rows);
        }
        $index = $this->xml();
        $this->assertSame('sitemapindex', $index->getName());
        $this->assertSame(['https://novelions.ro/sitemap-pages-1.xml',
            'https://novelions.ro/sitemap-categories-1.xml', 'https://novelions.ro/sitemap-categories-2.xml'],
            array_map('strval', $index->xpath('//*[local-name()="loc"]')));
        $first = $this->xml('/sitemap-categories-1.xml');
        $last = $this->xml('/sitemap-categories-2.xml');
        $this->assertCount(SitemapController::PAGE_SIZE, $first->url);
        $this->assertCount(1, $last->url);
        $this->assertSame('https://novelions.ro/categorie/category-10001', (string) $last->url[0]->loc);
        $this->assertCount(count(StorefrontSeo::STATIC_ROUTES), $this->xml('/sitemap-pages-1.xml')->url);
        foreach (['/sitemap-categories-3.xml', '/sitemap-pages-2.xml', '/sitemap-products-1.xml',
            '/sitemap-categories-0.xml', '/sitemap-admin-1.xml'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }
}
