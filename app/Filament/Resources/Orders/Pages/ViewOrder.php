<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\InvoiceService;
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

            Action::make('generateAwb')
                ->label('Generează AWB')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Generare AWB')
                ->modalDescription('Integrarea cu Postis va fi activată în curând.')
                ->action(function () {

                    Notification::make()
                        ->title('Postis încă nu este configurat.')
                        ->body('În etapa următoare vom conecta API-ul și AWB-ul se va genera automat.')
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

                        'Factura-' . $this->record->order_number . '.pdf'

                    );

                }),

        ];
    }
}