<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->modalHeading('Șterge produsul')
                ->modalDescription('Produsul poate fi șters numai dacă nu apare în comenzi istorice.')
                ->requiresConfirmation()
                ->before(function (DeleteAction $action, Product $record): void {
                    if (! $record->orderItems()->exists()) {
                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('Produsul nu poate fi șters')
                        ->body('Produsul apare în comenzi istorice. Dezactivează-l pentru a-l retrage din vânzare.')
                        ->send();

                    $action->halt();
                })
                ->successNotificationTitle('Produsul a fost șters.'),
        ];
    }
}
