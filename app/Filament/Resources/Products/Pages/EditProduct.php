<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Services\AdminProductStock;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    #[Locked]
    public int $originalStock = 0;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->originalStock = (int) $data['stock_quantity'];

        return $data;
    }

    public function refreshFormData(array $statePaths): void
    {
        // A partial metadata refresh must not advance the inventory baseline.
        $data = $this->getRecord()->attributesToArray();
        $this->form->fillPartially($data, $statePaths);
        if (in_array('stock_quantity', $statePaths, true)) {
            $this->originalStock = (int) $data['stock_quantity'];
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $updated = app(AdminProductStock::class)->save($record, $data, $this->originalStock);
        $this->record = $updated;
        $this->originalStock = (int) $updated->stock_quantity;
        $this->data['stock_quantity'] = $this->originalStock;

        return $updated;
    }

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
