<?php

namespace App\Http\Controllers;

use App\Mail\ReturnRequestConfirmationMail;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\WithdrawalDeadline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ReturnRequestController extends Controller
{
    /**
     * Afișează formularul pentru solicitarea unui retur.
     */
    public function create(Order $order, WithdrawalDeadline $deadlines)
    {
        abort_if($order->user_id !== auth()->id(), 403);

        if (
            $order->status !== 'delivered' ||
            ! $order->delivered_at
        ) {
            abort(404);
        }

        $withdrawalDeadline = $deadlines->forDelivery($order->delivered_at);

        return view('account.returns.create', compact('order', 'withdrawalDeadline'));

    }

    /**
     * Salvează solicitarea de retur.
     */
    public function store(Request $request, Order $order, WithdrawalDeadline $deadlines)
    {
        abort_if($order->user_id !== auth()->id(), 403);

        if (
            $order->status !== 'delivered' ||
            ! $order->delivered_at
        ) {
            abort(404);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:withdrawal,nonconformity'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000', 'required_if:type,nonconformity'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'bank_iban' => ['nullable', 'string', 'max:34'],
            'accept_bank_transfer' => ['nullable'],
        ]);

        if ($order->payment_method !== 'stripe' && $validated['type'] === 'withdrawal') {
            $request->validate(['bank_iban' => ['required', 'string', 'max:34'], 'accept_bank_transfer' => ['accepted']]);
        }

        if ($validated['type'] === 'withdrawal' && now()->timezone('Europe/Bucharest')->greaterThan($deadlines->forDelivery($order->delivered_at))) {
            return redirect()
                ->route('my-orders.show', $order)
                ->with(
                    'error',
                    'Perioada de 14 zile pentru exercitarea dreptului de retur a expirat.'
                );
        }

        $selected = collect($validated['items'])->filter(fn ($qty) => (int) $qty > 0);
        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Selectează cel puțin un produs și o cantitate.']);
        }

        $return = DB::transaction(function () use ($order, $validated, $selected, $request) {
            $return = ReturnRequest::create([
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'type' => $validated['type'],
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'requested',
                'requested_at' => now(),
                'refund_method' => $order->payment_method === 'stripe' ? 'stripe' : 'bank_transfer',
                'refund_status' => null,
                'bank_iban' => $order->payment_method === 'stripe' ? null : ($validated['bank_iban'] ?? null),
                'bank_transfer_accepted_at' => $order->payment_method === 'stripe' || ! $request->boolean('accept_bank_transfer') ? null : now(),
            ]);

            $amount = 0;
            foreach ($selected as $orderItemId => $quantity) {
                $item = $order->items()->whereKey($orderItemId)->lockForUpdate()->firstOrFail();
                $already = $item->returnItems()->whereHas('returnRequest', fn ($q) => $q->whereNotIn('status', ['rejected']))->sum('quantity');
                if ($quantity > $item->quantity - $already) {
                    throw ValidationException::withMessages(['items.'.$orderItemId => 'Cantitatea depășește cantitatea disponibilă pentru retur.']);
                }
                $line = round((float) $item->price * (int) $quantity, 2);
                $return->items()->create(['order_item_id' => $item->id, 'quantity' => $quantity, 'unit_price' => $item->price, 'line_refund_amount' => $line]);
                $amount += $line;
            }
            if ($validated['type'] === 'withdrawal') {
                $priorWithdrawals = $order->returnRequests()->where('type', 'withdrawal')
                    ->whereKeyNot($return->id)->where('status', '!=', 'rejected')->with('items')->get();
                $allWithdrawn = $order->items()->get()->every(function ($item) use ($selected, $priorWithdrawals) {
                    $prior = $priorWithdrawals->sum(fn ($previousReturn) => $previousReturn->items->where('order_item_id', $item->id)->sum('quantity'));

                    return $prior + (int) ($selected[$item->id] ?? 0) === $item->quantity;
                });
                $shippingAlreadyRefunded = $priorWithdrawals->contains(fn ($previousReturn) => (float) $previousReturn->refund_amount > (float) $previousReturn->items->sum('line_refund_amount'));
                if ($allWithdrawn && ! $shippingAlreadyRefunded) {
                    $amount += (float) $order->shipping_cost;
                }
            }
            $return->update(['refund_amount' => $amount]);
            foreach ($request->file('photos', []) as $photo) {
                $return->photos()->create(['path' => $photo->store('return-photos', 'local')]);
            }

            return $return->load('order', 'items.orderItem');
        });

        Mail::to($order->email)->send(new ReturnRequestConfirmationMail($return));
        $return->update(['confirmation_sent_at' => now()]);

        return redirect()
            ->route('my-orders.show', $order)
            ->with(
                'success',
                'Solicitarea a fost înregistrată, iar confirmarea a fost trimisă pe email.'
            );
    }

    public function bankDetails(Request $request, ReturnRequest $return)
    {
        abort_unless($return->user_id === $request->user()->id, 403);
        abort_unless($return->refund_method === 'bank_transfer' && $return->status !== 'rejected', 404);

        return view('account.returns.bank-details', compact('return'));
    }

    public function saveBankDetails(Request $request, ReturnRequest $return)
    {
        abort_unless($return->user_id === $request->user()->id, 403);
        abort_unless($return->refund_method === 'bank_transfer' && $return->status !== 'rejected', 404);
        $data = $request->validate([
            'bank_iban' => ['required', 'string', 'max:34'],
            'accept_bank_transfer' => ['accepted'],
        ]);
        $return->update([
            'bank_iban' => strtoupper(preg_replace('/\s+/', '', $data['bank_iban'])),
            'bank_transfer_accepted_at' => now(),
        ]);

        return redirect()->route('my-orders.show', $return->order)->with('success', 'IBAN-ul și acordul pentru transfer au fost înregistrate.');
    }
}
