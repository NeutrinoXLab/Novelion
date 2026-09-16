<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\InvoiceService;
use App\Services\StripeService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [

            EditAction::make(),

            Action::make('refund')
                ->label('Rambursează plata')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(function () {
                    if ($this->record->payment_status !== 'paid' || $this->record->payment_method !== 'stripe' || $this->record->stripe_refund_id) {
                        return false;
                    }

                    /*
                     * Dacă există un retur activ sau rambursat,
                     * plata nu mai poate fi rambursată manual
                     * din pagina comenzii.
                     */
                    return ! $this->record->returnRequests()
                        ->whereNotIn('status', ['rejected'])
                        ->exists();
                })
                ->requiresConfirmation()
                ->modalHeading('Rambursare plată')
                ->modalDescription(
                    fn () => 'Ești sigur că vrei să rambursezi suma totală de '
                        .number_format(
                            (float) $this->record->total,
                            2,
                            ',',
                            '.'
                        )
                        .' lei pentru comanda '
                        .$this->record->order_number
                        .'?'
                )
                ->modalSubmitActionLabel('Da, rambursează')
                ->action(function () {

                    try {

                        /*
                         * Protecție suplimentară:
                         * verificăm din nou existența unui retur activ sau rambursat
                         * înainte de a trimite cererea către Stripe.
                         */
                        $hasActiveReturn = $this->record
                            ->returnRequests()
                            ->whereNotIn('status', ['rejected'])
                            ->exists();

                        if ($hasActiveReturn) {
                            throw new \RuntimeException(
                                'Această comandă are deja un retur activ sau rambursat.'
                            );
                        }

                        app(StripeService::class)
                            ->refundPayment($this->record);

                        Notification::make()
                            ->title('Cererea de rambursare a fost înregistrată.')
                            ->body(
                                'Suma totală de '
                                .number_format(
                                    (float) $this->record->total,
                                    2,
                                    ',',
                                    '.'
                                )
                                .' lei este urmărită până la confirmarea Stripe.'
                            )
                            ->success()
                            ->send();

                        $this->refresh();

                    } catch (\Throwable $e) {

                        Notification::make()
                            ->title('Rambursarea a eșuat.')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                    }

                }),

            Action::make('generateAwb')
                ->label('Generează AWB')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Generare AWB')
                ->modalDescription(
                    'Integrarea cu Postis va fi activată în curând.'
                )
                ->action(function () {

                    Notification::make()
                        ->title('Postis încă nu este configurat.')
                        ->body(
                            'În etapa următoare vom conecta API-ul '
                            .'și AWB-ul se va genera automat.'
                        )
                        ->info()
                        ->send();

                }),

            Action::make('invoice')
                ->label('Descarcă factura')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {

                    $pdf = app(InvoiceService::class)
                        ->generate($this->record);

                    return response()->streamDownload(

                        fn () => print ($pdf->output()),

                        'Factura-'
                        .$this->record->order_number
                        .'.pdf'

                    );

                }),

        ];
    }
}
