<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')

            ->columns([

                ImageColumn::make('primary_image_path')
                    ->label('Imagine')
                    ->disk('public')
                    ->square()
                    ->height(60)
                    ->extraImgAttributes(['class' => 'object-contain bg-white'])
                    ->defaultImageUrl('https://placehold.co/60x60?text=No+Image'),

                TextColumn::make('name')
                    ->label('Produs')
                    ->searchable(['name', 'sku'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category.name')
                    ->label('Categorie')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('selling_price')
                    ->label('Preț')
                    ->money('RON')
                    ->sortable(),

                TextColumn::make('stock_quantity')
                    ->label('Stoc')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state <= 0 => 'danger',
                        $state <= 5 => 'warning',
                        default => 'success',
                    }),

                IconColumn::make('is_active')
                    ->label('Activ')
                    ->boolean(),

            ])

            ->filters([

                SelectFilter::make('category')
                    ->relationship('category', 'name'),

                SelectFilter::make('brand')
                    ->relationship('brand', 'name'),

            ])

            ->recordActions([

                ViewAction::make(),

                EditAction::make(),
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

            ]);
    }
}
