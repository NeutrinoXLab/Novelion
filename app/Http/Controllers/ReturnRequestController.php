<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;

class ReturnRequestController extends Controller
{
    /**
     * Afișează formularul pentru solicitarea unui retur.
     */
    public function create(Order $order)
    {
        abort_if($order->user_id !== auth()->id(), 403);

        if (
            $order->status !== 'delivered' ||
            $order->payment_status !== 'paid' ||
            ! $order->delivered_at
        ) {
            abort(404);
        }

        if ($order->delivered_at->addDays(30)->isPast()) {
            abort(404);
        }

        $existingRequest = $order->returnRequests()
            ->whereIn('status', [
                'requested',
                'approved',
                'received',
            ])
            ->exists();

        if ($existingRequest) {
            abort(404);
        }

return view('account.returns.create', compact('order'));

    }

    /**
     * Salvează solicitarea de retur.
     */
    public function store(Request $request, Order $order)
    {
        abort_if($order->user_id !== auth()->id(), 403);

        if (
            $order->status !== 'delivered' ||
            $order->payment_status !== 'paid' ||
            ! $order->delivered_at
        ) {
            abort(404);
        }

        if ($order->delivered_at->addDays(30)->isPast()) {
            return redirect()
                ->route('my-orders.show', $order)
                ->with(
                    'error',
                    'Perioada de 30 de zile pentru exercitarea dreptului de retur a expirat.'
                );
        }

        $existingRequest = $order->returnRequests()
            ->whereIn('status', [
                'requested',
                'approved',
                'received',
            ])
            ->exists();

        if ($existingRequest) {
            return redirect()
                ->route('my-orders.show', $order)
                ->with(
                    'error',
                    'Pentru această comandă există deja o solicitare de retur.'
                );
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        ReturnRequest::create([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'requested',
            'requested_at' => now(),
        ]);

        return redirect()
            ->route('my-orders.show', $order)
            ->with(
                'success',
                'Solicitarea de retur a fost trimisă. Te vom contacta pentru pașii următori.'
            );
    }
}