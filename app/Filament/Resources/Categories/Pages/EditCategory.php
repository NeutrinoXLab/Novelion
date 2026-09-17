<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->modalHeading('Șterge categoria')
                ->modalDescription('Categoria va fi ștearsă numai dacă nu are produse asociate.')
                ->requiresConfirmation()
                ->before(function (DeleteAction $action, Category $record): void {
                    if (! $record->products()->exists()) {
                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('Categoria nu poate fi ștearsă')
                        ->body('Mută, realocă sau elimină mai întâi produsele asociate acestei categorii.')
                        ->send();

                    $action->halt();
                })
                ->successNotificationTitle('Categoria a fost ștearsă.'),
        ];
    }
}
