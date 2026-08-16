<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Pagina "Contul meu".
     */
    public function index(): View
    {
        $user = auth()->user();

        $ordersCount = $user->orders()->count();

        $totalSpent = $user->orders()
            ->where('payment_status', 'paid')
            ->sum('total');

        $latestOrders = $user->orders()
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard.index', compact(
            'user',
            'ordersCount',
            'totalSpent',
            'latestOrders'
        ));
    }
}