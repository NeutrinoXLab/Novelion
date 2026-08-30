<?php

namespace App\Filament\Resources\Newsletters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NewslettersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subscribed_at')
                    ->label('Abonat la')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('unsubscribed_at')
                    ->label('Dezabonat la')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn ($record) =>
                        $record->unsubscribed_at ? 'Dezabonat' : 'Activ'
                    )
                    ->badge()
                    ->color(fn (string $state): string =>
                        $state === 'Activ' ? 'success' : 'danger'
                    ),
            ])
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
