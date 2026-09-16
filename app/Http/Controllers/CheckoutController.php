<?php

namespace App\Http\Controllers;

use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CheckoutController extends Controller
{
    protected OrderService $orderService;

    protected StripeService $stripeService;

    public function __construct(
        OrderService $orderService,
        StripeService $stripeService
    ) {
        $this->orderService = $orderService;
        $this->stripeService = $stripeService;
    }

    /**
     * Pagina de checkout.
     */
    public function index(CartService $cart)
    {
        if ($cart->count() === 0) {
            return redirect()->route('cart.index');
        }

        if (! $cart->canShip()) {
            return redirect()->route('cart.index')->with('error', 'Livrarea nu poate fi calculată pentru acest coș. Verifică din nou mai târziu sau contactează-ne.');
        }

        return view('checkout.index', [
            'items' => $cart->getCart(),

            // Doar produsele
            'subtotal' => $cart->subtotal(),

            // Transportul calculat după greutate și volum
            'shippingCost' => $cart->shippingCost(),
            'shippingName' => $cart->shippingName(),

            // Produse + transport
            'total' => $cart->total(),
        ]);
    }

    /**
     * Creează comanda și redirecționează către metoda de plată.
     */
    public function store(Request $request, CartService $cart)
    {
        if ($cart->count() === 0) {
            return redirect()->route('cart.index');
        }

        if (! $cart->canShip()) {
            return redirect()->route('cart.index')->with('error', 'Livrarea nu poate fi calculată; comanda nu poate fi finalizată.');
        }

        $validated = $request->validate([

            /*
            |--------------------------------------------------------------------------
            | CLIENT
            |--------------------------------------------------------------------------
            */

            'customer_type' => 'required|in:individual,company',

            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',

            'county' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:20',

            /*
            |--------------------------------------------------------------------------
            | PLATĂ
            |--------------------------------------------------------------------------
            */

            'payment_method' => 'required|in:cash,stripe',

            /*
            |--------------------------------------------------------------------------
            | DATE FIRMĂ
            |--------------------------------------------------------------------------
            */

            'company_name' => 'required_if:customer_type,company|nullable|string|max:255',

            'company_vat' => 'required_if:customer_type,company|nullable|string|max:100',

            'company_registration' => 'nullable|string|max:100',

            'company_address' => 'required_if:customer_type,company|nullable|string|max:255',

            'company_city' => 'required_if:customer_type,company|nullable|string|max:100',

            'company_county' => 'required_if:customer_type,company|nullable|string|max:100',

            /*
            |--------------------------------------------------------------------------
            | ADRESĂ LIVRARE
            |--------------------------------------------------------------------------
            */

            'shipping_first_name' => 'nullable|string|max:100',
            'shipping_last_name' => 'nullable|string|max:100',
            'shipping_phone' => 'nullable|string|max:30',

            'shipping_county' => 'nullable|string|max:100',
            'shipping_city' => 'nullable|string|max:100',
            'shipping_address' => 'nullable|string|max:255',
            'shipping_postal_code' => 'nullable|string|max:20',

            /*
            |--------------------------------------------------------------------------
            | OBSERVAȚII
            |--------------------------------------------------------------------------
            */

            'notes' => 'nullable|string|max:1000',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Dacă nu există o adresă de livrare separată,
        | folosim automat adresa de facturare.
        |--------------------------------------------------------------------------
        */

        if (empty($validated['shipping_address'])) {

            $validated['shipping_first_name'] =
                $validated['first_name'];

            $validated['shipping_last_name'] =
                $validated['last_name'];

            $validated['shipping_phone'] =
                $validated['phone'];

            $validated['shipping_county'] =
                $validated['county'];

            $validated['shipping_city'] =
                $validated['city'];

            $validated['shipping_address'] =
                $validated['address'];

            $validated['shipping_postal_code'] =
                $validated['postal_code'] ?? null;
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Creăm comanda
            |--------------------------------------------------------------------------
            */

            $order = $this->orderService->create(
                $validated,
                $cart
            );

            /*
            |--------------------------------------------------------------------------
            | Ramburs
            |--------------------------------------------------------------------------
            */

            if ($validated['payment_method'] === 'cash') {

                Mail::to($order->email)->send(new OrderPlacedMail($order->load('items')));

                return redirect()
                    ->route('checkout.success', $order);
            }

            /*
            |--------------------------------------------------------------------------
            | Stripe
            |--------------------------------------------------------------------------
            */

            $session = $this->stripeService
                ->createCheckoutSession($order);

            return redirect($session->url);

        } catch (\Exception $e) {

            report($e);

            /*
            |--------------------------------------------------------------------------
            | Dacă plata Stripe nu poate fi inițiată,
            | eliberăm stocul rezervat pentru comandă.
            |--------------------------------------------------------------------------
            */

            if (isset($order)) {
                $this->orderService->markAsFailed($order);
            }

            return back()
                ->withInput()
                ->with(
                    'error',
                    'A apărut o eroare la procesarea comenzii. Te rugăm să încerci din nou.'
                );
        }
    }

    /**
     * Revenirea din Checkout.
     *
     * Redirectul browserului nu este o confirmare de plată. Webhook-ul Stripe
     * este singura sursă de adevăr pentru plata cu cardul.
     */
    public function success(Order $order, CartService $cart)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        if ($order->payment_method === 'cash') {
            $cart->clear();

            return redirect()
                ->route('home')
                ->with(
                    'success',
                    'Comanda a fost înregistrată cu succes!'
                );
        }

        if ($order->payment_status === 'paid') {
            $cart->clear();

            return redirect()
                ->route('home')
                ->with(
                    'success',
                    'Plata a fost confirmată, iar comanda ta este în procesare.'
                );
        }

        if ($order->payment_status === 'pending') {
            return redirect()
                ->route('my-orders.show', $order)
                ->with(
                    'info',
                    'Plata este în curs de confirmare. Vei vedea starea actualizată a comenzii în câteva momente.'
                );
        }

        return redirect()
            ->route('checkout.index')
            ->with(
                'error',
                'Plata nu a putut fi confirmată. Te rugăm să încerci din nou.'
            );
    }

    /**
     * Stripe revine prin GET; navigarea nu poate anula o comandă.
     */
    public function cancel(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        return redirect()->route('my-orders.show', $order)->with(
            'info',
            'Plata nu a fost confirmată. Poți anula comanda din pagina ei; până atunci stocul rămâne rezervat.'
        );
    }

    public function cancelOrder(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $order->refresh();

        if ($order->payment_method !== 'stripe' || $order->payment_status === 'paid') {
            return redirect()->route('my-orders.show', $order)->with(
                'info',
                'Comanda nu mai poate fi anulată din acest formular.'
            );
        }

        $this->orderService->markAsFailed($order);

        return redirect()
            ->route('checkout.index')
            ->with(
                'error',
                'Plata a fost anulată.'
            );
    }
}
