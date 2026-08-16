<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class MyOrderController extends Controller
{
    /**
     * Lista comenzilor utilizatorului autentificat.
     */
    public function index()
    {
        $orders = Order::where('user_id', auth()->id())
            ->latest()
            ->paginate(10);

        return view('account.orders.index', compact('orders'));
    }

    /**
     * Afișează o comandă.
     */
    public function show(Order $order)
    {
        // Utilizatorul poate vedea doar comenzile lui
        abort_if($order->user_id !== auth()->id(), 403);

        $order->load('items');

        return view('account.orders.show', compact('order'));
    }
}