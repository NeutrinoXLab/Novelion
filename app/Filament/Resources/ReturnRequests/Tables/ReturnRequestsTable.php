<?php

namespace App\Filament\Resources\ReturnRequests\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReturnRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Comandă')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reason')
                    ->label('Motiv')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'requested' => 'Solicitat',
                        'approved' => 'Aprobat',
                        'received' => 'Primit',
                        'refunded' => 'Rambursat',
                        'rejected' => 'Respins',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'requested' => 'warning',
                        'approved' => 'info',
                        'received' => 'primary',
                        'refunded' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('requested_at')
                    ->label('Solicitat la')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}