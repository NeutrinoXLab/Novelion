<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Produse comandate';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')

            ->columns([

                Tables\Columns\TextColumn::make('product_name')
                    ->label('Produs')
                    ->searchable(),

                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantitate')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Preț unitar')
                    ->money('RON'),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('RON'),

            ])

            ->headerActions([])

            ->actions([])

            ->bulkActions([]);
    }
}