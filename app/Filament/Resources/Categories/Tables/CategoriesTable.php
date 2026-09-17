<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                ImageColumn::make('image_path')
                    ->disk('public')
                    ->square()
                    ->extraImgAttributes(['class' => 'object-contain bg-white']),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('seo_title')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
            ]);
    }
}
