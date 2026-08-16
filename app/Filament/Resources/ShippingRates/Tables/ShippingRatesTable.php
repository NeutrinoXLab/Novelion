<?php

namespace App\Filament\Resources\ShippingRates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShippingRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('name')
                    ->label('Tip transport')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('max_weight')
                    ->label('Greutate maximă')
                    ->suffix(' kg')
                    ->sortable(),

                TextColumn::make('max_volume')
                    ->label('Volum maxim')
                    ->suffix(' cm³')
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Preț transport')
                    ->money('RON')
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Activ')
                    ->boolean()
                    ->sortable(),

            ])
            ->defaultSort('sort_order')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}