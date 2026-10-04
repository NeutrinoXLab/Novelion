<?php

namespace App\Services;

use App\Models\CheckoutAttempt;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\Checkout\Session;

class CheckoutAttempts
{
    public function issue(CartService $cart): CheckoutAttempt
    {
        return DB::transaction(function () use ($cart): CheckoutAttempt {
            // Serializes issuing forms, including two tabs with no session token yet.
            User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $hash = $this->cartHash($cart);
            $sessionHash = $this->sessionHash();
            // A cancelled/failed attempt can be replaced by a freshly issued form,
            // but a replay of its old token still resolves to the old order.
            CheckoutAttempt::query()->where('user_id', auth()->id())->where('session_hash', $sessionHash)
                ->whereNull('retired_at')->whereIn('order_id', Order::query()
                ->where('status', 'cancelled')->orWhereIn('payment_status', ['failed', 'refunded'])->select('id'))
                ->update(['retired_at' => now()]);
            $attempt = CheckoutAttempt::query()->where('user_id', auth()->id())
                ->where('session_hash', $sessionHash)->where('cart_hash', $hash)
                ->whereNull('retired_at')->where(function ($query): void {
                    $query->whereNotNull('order_id')->orWhere('expires_at', '>', now());
                })->latest('id')->first();

            return $attempt ?? CheckoutAttempt::create([
                'token' => (string) Str::uuid(), 'user_id' => auth()->id(),
                'session_hash' => $sessionHash, 'cart_hash' => $hash,
                'expires_at' => now()->addHours(2),
            ]);
        });
    }

    public function retire(): void
    {
        if (! auth()->check()) {
            return;
        }
        DB::transaction(function (): void {
            User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            CheckoutAttempt::query()->where('user_id', auth()->id())
                ->where('session_hash', $this->sessionHash())
                ->whereNull('retired_at')->update(['retired_at' => now()]);
        });
    }

    public function order(string $token, array $data, CartService $cart): Order
    {
        unset($data['checkout_token']);
        ksort($data);
        $requestHash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($token, $data, $cart, $requestHash): Order {
            $attempt = CheckoutAttempt::query()->where('token', $token)
                ->where('user_id', auth()->id())->lockForUpdate()->first();
            if (! $attempt) {
                $this->reject('Formularul de checkout nu este valid. Reîncarcă pagina.');
            }
            if ($attempt->request_hash !== null && ! hash_equals($attempt->request_hash, $requestHash)) {
                $this->reject('Acest checkout a fost deja înregistrat cu alte date. Verifică pagina comenzii înainte de o nouă comandă.');
            }
            // Replays remain valid after the cart is cleared, changed or the form expires.
            if ($attempt->order_id !== null) {
                return Order::query()->whereKey($attempt->order_id)->firstOrFail();
            }
            if ($attempt->retired_at || $attempt->expires_at->isPast()
                || ! hash_equals($attempt->cart_hash, $this->cartHash($cart)) || $cart->count() === 0) {
                $this->reject('Coșul sau formularul de checkout s-a schimbat. Reîncarcă pagina.');
            }

            $order = app(OrderService::class)->create($data, $cart);
            $attempt->update(['order_id' => $order->id, 'request_hash' => $requestHash]);

            return $order;
        });
    }

    public function stripeSession(Order $order): Session
    {
        // Commit the exact request BEFORE any external side effect. A lost API reply
        // or failed local save must retry the same parameters and the same Stripe key.
        DB::transaction(function () use ($order): void {
            $attempt = CheckoutAttempt::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            if ($attempt->stripe_parameters === null) {
                $parameters = app(StripeService::class)->checkoutParameters($order);
                $parameters['metadata']['order_id'] = (string) $order->id;
                $parameters['client_reference_id'] = $attempt->token;
                $parameters['metadata'] += [
                    'checkout_attempt' => $attempt->token,
                    'user_id' => (string) $attempt->user_id,
                    'request_hash' => $attempt->request_hash,
                ];
                $parameters['metadata']['checkout_fingerprint'] = self::stripeFingerprint($parameters);
                $attempt->update([
                    'stripe_parameters' => $parameters,
                    'stripe_started_at' => now(),
                ]);
            }
        });

        return DB::transaction(function () use ($order): Session {
            $attempt = CheckoutAttempt::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->payment_method !== 'stripe' || $order->payment_status !== 'pending' || $order->status === 'cancelled') {
                throw new \RuntimeException('Comanda nu mai permite inițierea plății. Verifică pagina comenzii.');
            }
            // Stripe can prune keys after 24h. Fail closed before that boundary.
            if ($attempt->stripe_started_at->lte(now()->subHours(23))) {
                throw new \RuntimeException('Inițierea plății necesită verificare. Nu crea o comandă nouă înainte de clarificarea celei existente.');
            }
            if ($order->stripe_session_id !== null && $attempt->stripe_session_url !== null) {
                return Session::constructFrom(['id' => $order->stripe_session_id, 'url' => $attempt->stripe_session_url]);
            }
            $session = app(StripeService::class)->createCheckoutSession($order, $attempt->stripe_parameters, 'novelion-checkout-'.$attempt->token);
            if (! is_string($session->url) || $session->url === '') {
                throw new \RuntimeException('Stripe nu a returnat adresa de plată.');
            }
            $attempt->update(['stripe_session_url' => $session->url]);

            return $session;
        });
    }

    public static function stripeFingerprint(array $parameters): string
    {
        // MySQL JSON storage can reorder object keys. Preserve list order, but
        // canonicalize every object before hashing the durable request.
        $canonicalize = function (array $value) use (&$canonicalize): array {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as $key => $item) {
                if (is_array($item)) {
                    $value[$key] = $canonicalize($item);
                }
            }

            return $value;
        };

        return hash('sha256', json_encode($canonicalize($parameters), JSON_THROW_ON_ERROR));
    }

    private function cartHash(CartService $cart): string
    {
        $quantities = [];
        foreach ($cart->getCart() as $item) {
            $id = (int) $item['id'];
            $quantities[$id] = ($quantities[$id] ?? 0) + $item['quantity'];
        }
        ksort($quantities);

        return hash('sha256', json_encode($quantities, JSON_THROW_ON_ERROR));
    }

    private function sessionHash(): string
    {
        // Persist across session-ID regeneration. Initial parallel requests derive
        // the same scope from the browser's existing session ID.
        if (! session()->has('checkout_scope')) {
            session()->put('checkout_scope', hash('sha256', session()->getId()));
        }

        return session()->get('checkout_scope');
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['checkout_token' => $message]);
    }
}
