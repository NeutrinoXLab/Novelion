<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\StripeService;
use Illuminate\Http\Request;

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

            'company_name' =>
                'required_if:customer_type,company|nullable|string|max:255',

            'company_vat' =>
                'required_if:customer_type,company|nullable|string|max:100',

            'company_registration' =>
                'nullable|string|max:100',

            'company_address' =>
                'required_if:customer_type,company|nullable|string|max:255',

            'company_city' =>
                'required_if:customer_type,company|nullable|string|max:100',

            'company_county' =>
                'required_if:customer_type,company|nullable|string|max:100',

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
                $validated['postal_code'];
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

            return back()
                ->withInput()
                ->with(
                    'error',
                    'A apărut o eroare la procesarea comenzii. Te rugăm să încerci din nou.'
                );
        }
    }

    /**
     * Plata finalizată.
     */
    public function success(Order $order, CartService $cart)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $cart->clear();

        return redirect()
            ->route('home')
            ->with(
                'success',
                'Comanda a fost înregistrată cu succes!'
            );
    }

    /**
     * Plata anulată.
     */
    public function cancel(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
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