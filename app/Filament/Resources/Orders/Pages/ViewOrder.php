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
                ->visible(fn () => $this->record->payment_status === 'paid')
                ->requiresConfirmation()
                ->modalHeading('Rambursare plată')
                ->modalDescription(
                    fn () => 'Ești sigur că vrei să rambursezi suma de '
                        . number_format((float) $this->record->subtotal, 2, ',', '.')
                        . ' lei pentru comanda '
                        . $this->record->order_number
                        . '?'
                )
                ->modalSubmitActionLabel('Da, rambursează')
                ->action(function () {

                    try {

                        app(StripeService::class)
                            ->refundPayment($this->record);

                        Notification::make()
                            ->title('Plata a fost rambursată.')
                            ->body(
                                'Suma de '
                                . number_format(
                                    (float) $this->record->subtotal,
                                    2,
                                    ',',
                                    '.'
                                )
                                . ' lei a fost rambursată prin Stripe.'
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
                            . 'și AWB-ul se va genera automat.'
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

                        fn () => print($pdf->output()),

                        'Factura-'
                        . $this->record->order_number
                        . '.pdf'

                    );

                }),

        ];
    }
}