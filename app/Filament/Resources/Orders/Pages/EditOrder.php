<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\AdminOrderLifecycle;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [ViewAction::make()];
        foreach ([
            'processing' => 'Începe procesarea',
            'shipped' => 'Confirmă expedierea',
            'delivered' => 'Confirmă livrarea',
            'cash_paid' => 'Confirmă încasarea ramburs',
            'cancelled' => 'Anulează comanda neplătită',
        ] as $target => $label) {
            $actions[] = Action::make($target)->label($label)->requiresConfirmation()
                ->modalDescription($target === 'cash_paid'
                    ? 'Confirmă numai după verificarea încasării efective.'
                    : 'Operația se verifică pe starea actuală. Plățile încasate se rambursează prin fluxul dedicat din pagina comenzii/returului.')
                ->action(function () use ($target): void {
                    $this->authorizeAccess();
                    try {
                        $this->record = app(AdminOrderLifecycle::class)->transition($this->record, $target);
                    } catch (ValidationException $exception) {
                        Notification::make()->title(collect($exception->errors())->flatten()->first())->danger()->send();

                        return;
                    }
                    $this->refreshFormData(['status', 'payment_status']);
                    Notification::make()->title('Operația a fost înregistrată.')->success()->send();
                });
        }

        return $actions;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Never persist lifecycle/financial fields, even from a forged or stale form.
        $record->update(Arr::only($data, [
            'customer_type', 'first_name', 'last_name', 'email', 'phone',
            'county', 'city', 'address', 'postal_code', 'company_name', 'company_vat',
            'company_registration', 'company_address', 'company_city', 'company_county',
            'shipping_first_name', 'shipping_last_name', 'shipping_phone',
            'shipping_county', 'shipping_city', 'shipping_address', 'shipping_postal_code', 'notes',
        ]));

        return $record->refresh();
    }
}
