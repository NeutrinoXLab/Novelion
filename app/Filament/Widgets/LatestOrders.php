<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestOrders extends TableWidget
{
    protected static ?string $heading = 'Ultimele comenzi';

    protected bool $isTableSearchable = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => Order::query()->latest()->limit(5)
            )

            ->columns([

                Tables\Columns\TextColumn::make('order_number')
                    ->label('Nr. comandă')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('first_name')
                    ->label('Client')
                    ->formatStateUsing(fn ($state, Order $record) => $record->first_name . ' ' . $record->last_name),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('RON'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'primary' => 'shipped',
                        'success' => 'delivered',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'În așteptare',
                        'processing' => 'În procesare',
                        'shipped' => 'Expediată',
                        'delivered' => 'Livrată',
                        'cancelled' => 'Anulată',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i'),

            ])

            ->paginated(false)

->recordUrl(
    fn (Order $record): string => route(
        'filament.admin.resources.orders.view',
        $record
    )
);
    }
}