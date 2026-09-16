<?php

namespace App\Filament\Resources\ReturnRequests\Pages;

use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use App\Services\StripeService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditReturnRequest extends EditRecord
{
    protected static string $resource = ReturnRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $currentStatus = $this->record->status;
        $newStatus = $data['status'] ?? $currentStatus;

        /*
         * Nu permitem revenirea la un status anterior.
         */
        $statusOrder = [
            'requested' => 1,
            'approved' => 2,
            'received' => 3,
            'refunded' => 4,
            'rejected' => 4,
        ];

        if (
            isset($statusOrder[$currentStatus], $statusOrder[$newStatus]) &&
            $statusOrder[$newStatus] < $statusOrder[$currentStatus]
        ) {
            $data['status'] = $currentStatus;
            $newStatus = $currentStatus;
        }

        /*
         * Data aprobării se completează automat.
         */
        if ($newStatus === 'approved' && ! $this->record->approved_at) {
            $data['approved_at'] = now();
        }

        /*
         * Data primirii se completează automat.
         */
        if ($newStatus === 'received' && ! $this->record->received_at) {
            $data['received_at'] = now();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refundReturn')
                ->label('Rambursează returul')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'received' && $this->record->order->payment_method === 'stripe' && ! $this->record->stripe_refund_id)
                ->requiresConfirmation()
                ->modalHeading('Rambursare retur')
                ->modalDescription(
                    fn () => 'Inițiezi rambursarea sumei de '.$this->record->refund_amount.' RON pentru returul din comanda '
                        .$this->record->order->order_number
                        .' prin Stripe?'
                )
                ->modalSubmitActionLabel('Da, rambursează')
                ->action(function () {
                    try {
                        /*
                         * Verificăm din nou statusul înainte de refund.
                         * Protejează și împotriva unei a doua cereri
                         * după ce pagina a rămas deschisă.
                         */
                        $this->record->refresh();

                        if ($this->record->status !== 'received') {
                            throw new \RuntimeException(
                                'Returul nu mai poate fi rambursat deoarece statusul său s-a schimbat.'
                            );
                        }

                        /*
                         * Verificăm dacă există deja un refund Stripe.
                         */
                        if ($this->record->stripe_refund_id) {
                            throw new \RuntimeException(
                                'Acest retur a fost deja rambursat prin Stripe.'
                            );
                        }

                        /*
                         * 1. Efectuăm mai întâi refund-ul Stripe.
                         *
                         * Dacă Stripe refuză operațiunea, nu atingem
                         * stocul și returul rămâne în statusul received.
                         */
                        $refund = app(StripeService::class)
                            ->refundReturn($this->record);

                        /*
                         * Reîncărcăm returul pentru a avea datele actualizate.
                         */
                        $this->record->refresh();

                        Notification::make()
                            ->title('Rambursarea a fost inițiată.')
                            ->body(
                                'Plata pentru comanda '
                                .$this->record->order->order_number
                                .' este urmărită până la confirmarea Stripe.'
                                .' ID refund: '
                                .$refund->id
                            )
                            ->success()
                            ->send();

                        $this->refresh();

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Rambursarea returului a eșuat.')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('bankRefund')
                ->label('Evidență transfer bancar')
                ->visible(fn () => $this->record->status === 'received' && $this->record->refund_method === 'bank_transfer' && $this->record->bank_transfer_accepted_at !== null)
                ->form([TextInput::make('refund_status')->label('Stare transfer')->required()->datalist(['initiated', 'processing', 'completed', 'failed'])])
                ->action(function (array $data) {
                    if (! in_array($data['refund_status'], ['initiated', 'processing', 'completed', 'failed'], true)) {
                        throw new \RuntimeException('Stare invalidă.');
                    }
                    $this->record->update([
                        'refund_status' => $data['refund_status'],
                        'status' => $data['refund_status'] === 'completed' ? 'refunded' : 'received',
                        'refunded_at' => $data['refund_status'] === 'completed' ? now() : null,
                    ]);
                    if ($data['refund_status'] === 'completed') {
                        $this->record->restoreStock();
                    }
                    Notification::make()->title('Starea transferului a fost înregistrată.')->success()->send();
                }),
        ];
    }
}
