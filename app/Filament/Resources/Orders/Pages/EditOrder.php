<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\OrderService;
use App\Services\StripeService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        if (
            $this->record->status !== 'cancelled' &&
            ($this->data['status'] ?? null) === 'cancelled'
        ) {
            /*
             * Dacă o comandă Stripe este deja plătită,
             * efectuăm mai întâi refund-ul Stripe.
             */
            if (
                $this->record->payment_method === 'stripe' &&
                $this->record->payment_status === 'paid'
            ) {
                app(StripeService::class)
                    ->refundPayment($this->record);
            }

            app(OrderService::class)->cancel($this->record);
        }

        if (
            $this->record->status !== 'delivered' &&
            ($this->data['status'] ?? null) === 'delivered' &&
            $this->record->delivered_at === null
        ) {
            $this->record->delivered_at = now();
        }
    }
}
