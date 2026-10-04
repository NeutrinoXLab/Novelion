<?php

use App\Services\CartService;
use App\Services\CheckoutAttempts;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

// Runs only against the generated fixture DB. No migrations or real Stripe calls.
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = $argv[2];
$fixture = json_decode(file_get_contents($directory.'/request.json'), true, flags: JSON_THROW_ON_ERROR);
config([
    'database.default' => 'sqlite', 'database.connections.sqlite.database' => $directory.'/fixture.sqlite',
    'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 100,
    'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null', 'mail.default' => 'array',
    'services.stripe.secret' => 'sk_test_f2',
]);
DB::purge();
if (! str_contains(realpath($directory), 'novelion-f2-') || ! is_file($directory.'/request.json')) {
    throw new LogicException('Concurrency worker requires a generated fixture database.');
}
Auth::onceUsingId($fixture['user_id']);
session()->put('cart', $fixture['cart']);
ApiRequestor::setHttpClient(new class($directory) implements ClientInterface
{
    public function __construct(private string $directory) {}

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $key = null;
        foreach ($headers as $header) {
            if (str_starts_with($header, 'Idempotency-Key: ')) {
                $key = substr($header, strlen('Idempotency-Key: '));
            }
        }
        if ($key === null) {
            throw new LogicException('Missing Stripe idempotency key.');
        }
        $file = fopen($this->directory.'/stripe.json', 'c+');
        flock($file, LOCK_EX);
        $contents = stream_get_contents($file);
        $sessions = $contents === '' ? [] : json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (isset($sessions[$key]) && $sessions[$key]['params'] !== $params) {
            throw new LogicException('Stripe parameters changed under the same key.');
        }
        $sessions[$key] ??= ['params' => $params, 'response' => [
            'id' => 'cs_concurrent_f2', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/f2/concurrent',
        ]];
        ftruncate($file, 0);
        rewind($file);
        fwrite($file, json_encode($sessions, JSON_THROW_ON_ERROR));
        fflush($file);
        flock($file, LOCK_UN);
        fclose($file);

        return [json_encode($sessions[$key]['response'], JSON_THROW_ON_ERROR), 200, []];
    }
});
touch($directory.'/ready-'.$argv[3]);
$deadline = microtime(true) + 15;
while (! is_file($directory.'/start')) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Concurrency barrier timed out.');
    }
    usleep(10000);
    clearstatcache();
}
for ($attempt = 0; $attempt < 30; $attempt++) {
    try {
        $service = app(CheckoutAttempts::class);
        $order = $service->order($fixture['data']['checkout_token'], $fixture['data'], app(CartService::class));
        $session = $order->payment_method === 'stripe' ? $service->stripeSession($order) : null;
        echo json_encode(['order_id' => $order->id, 'session_id' => $session?->id, 'retries' => $attempt], JSON_THROW_ON_ERROR);
        exit(0);
    } catch (Throwable $exception) {
        // Models the browser retrying a rejected SQLite busy transaction.
        // The actual application does not hide DB failures or spin on them.
        if (! str_contains(strtolower($exception->getMessage()), 'database is locked') || DB::transactionLevel() !== 0) {
            throw $exception;
        }
        usleep(50000);
    }
}
throw new RuntimeException('Concurrent retry budget exhausted.');
