<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Providers\AppServiceProvider;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class TrustedHostTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_PROTO);
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/host-security-check', fn (Request $request) => response()->json([
            'asset' => asset('build/test.js'),
            'login' => route('login'),
            'success' => route('checkout.success', ['order' => 123]).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel' => route('checkout.cancel', ['order' => 123]),
            'webhook' => route('stripe.webhook'),
            'reset' => route('password.reset', ['token' => 'test-token']),
            'secure' => $request->isSecure(),
        ]));
    }

    private function production(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_client_forwarded_hosts_cannot_change_generated_urls_or_redirects(): void
    {
        $this->production();

        $headers = [
            'X-Forwarded-Host' => 'audit-novelion.invalid',
            'Forwarded' => 'host=audit-novelion.invalid;proto=http',
            'X-Forwarded-Port' => '1234',
            'X-Forwarded-Prefix' => '/attacker',
        ];

        $response = $this->withHeaders($headers)->get('https://novelions.ro/host-security-check');
        $response->assertOk()
            ->assertJsonPath('asset', 'https://novelions.ro/build/test.js')
            ->assertJsonPath('login', 'https://novelions.ro/login')
            ->assertJsonPath('success', 'https://novelions.ro/checkout/success/123?session_id={CHECKOUT_SESSION_ID}')
            ->assertJsonPath('cancel', 'https://novelions.ro/checkout/cancel/123')
            ->assertJsonPath('webhook', 'https://novelions.ro/stripe/webhook')
            ->assertJsonPath('reset', 'https://novelions.ro/reset-password/test-token');

        $this->withHeaders($headers)->get('https://novelions.ro/checkout')
            ->assertRedirect('https://novelions.ro/login');
    }

    public function test_only_verified_proxy_can_supply_https_and_never_a_forwarded_host(): void
    {
        config(['trustedproxy.proxies' => ['192.0.2.10']]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'audit-novelion.invalid', 'Forwarded' => 'host=audit-novelion.invalid'])
            ->get('http://novelions.ro/host-security-check')
            ->assertOk()->assertJsonPath('secure', true)
            ->assertJsonPath('login', 'https://novelions.ro/login');

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->get('http://novelions.ro/host-security-check')
            ->assertOk()->assertJsonPath('secure', false)
            ->assertJsonPath('login', 'http://novelions.ro/login');
    }

    public function test_production_accepts_www_before_server_redirect(): void
    {
        $this->production();
        $this->get('https://www.novelions.ro/host-security-check')->assertOk();
    }

    public function test_production_rejects_arbitrary_host_at_laravel_boundary(): void
    {
        $this->production();
        $this->get('https://audit-novelion.invalid/host-security-check')->assertStatus(400);
    }

    public function test_local_http_remains_functional(): void
    {
        $this->app['env'] = 'local';
        $this->get('http://novelions.test/host-security-check')
            ->assertOk()->assertJsonPath('login', 'http://novelions.test/login');
    }

    public function test_stripe_checkout_payload_uses_valid_host_without_network_or_database(): void
    {
        $this->production();
        config(['services.stripe.secret' => 'sk_test_fake']);
        $client = new class implements ClientInterface
        {
            public array $params = [];

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
            {
                $this->params = $params;

                return [json_encode(['id' => 'cs_test_local', 'object' => 'checkout.session']), 200, []];
            }
        };
        ApiRequestor::setHttpClient($client);
        $order = \Mockery::mock(Order::class)->makePartial();
        $order->id = 123;
        $order->shipping_cost = 0;
        $order->setRelation('items', collect([new OrderItem(['product_name' => 'Test', 'price' => 10, 'quantity' => 1])]));
        $order->shouldReceive('update')->once()->with(['stripe_session_id' => 'cs_test_local'])->andReturn(true);
        Route::get('/stripe-url-check', function () use ($order) {
            app(StripeService::class)->createCheckoutSession($order);

            return response('ok');
        });
        $this->withHeaders(['X-Forwarded-Host' => 'audit-novelion.invalid'])
            ->get('https://novelions.ro/stripe-url-check')->assertOk();

        $this->assertSame('https://novelions.ro/checkout/success/123?session_id={CHECKOUT_SESSION_ID}', $client->params['success_url']);
        $this->assertSame('https://novelions.ro/checkout/cancel/123', $client->params['cancel_url']);
    }
}
