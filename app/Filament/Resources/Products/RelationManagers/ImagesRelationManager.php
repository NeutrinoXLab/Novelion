<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductImage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Galerie imagini';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                FileUpload::make('image_path')
                    ->label('Imagine')
                    ->disk('public')
                    ->directory('products')
                    ->image()
                    ->imageEditor()
                    ->maxSize(5120)
                    ->required(),

                Toggle::make('is_primary')
                    ->label('Imagine principală')
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(function ($state, Get $get) {

                        if (! $state) {
                            return;
                        }

                        ProductImage::where('product_id', $this->ownerRecord->id)
                            ->update([
                                'is_primary' => false,
                            ]);
                    }),

                TextInput::make('sort_order')
                    ->label('Ordine')
                    ->numeric()
                    ->default(0),

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('image_path')

            ->defaultSort('sort_order')
            ->reorderable('sort_order')

            ->columns([

                ImageColumn::make('image_path')
                    ->label('Imagine')
                    ->disk('public')
                    ->square()
                    ->size(60),

                IconColumn::make('is_primary')
                    ->label('Principală')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),

            ])

            ->headerActions([

                CreateAction::make(),

            ])

            ->recordActions([

                EditAction::make(),

                DeleteAction::make(),

            ])

            ->toolbarActions([

                BulkActionGroup::make([

                    DeleteBulkAction::make(),

                ]),

            ]);
    }
}
