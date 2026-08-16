<?php

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Utilizator')
                    ->disabled(),

                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->label('Produs')
                    ->disabled(),

                Select::make('rating')
                    ->label('Rating')
                    ->options([
                        1 => '⭐',
                        2 => '⭐⭐',
                        3 => '⭐⭐⭐',
                        4 => '⭐⭐⭐⭐',
                        5 => '⭐⭐⭐⭐⭐',
                    ])
                    ->disabled(),

                Textarea::make('comment')
                    ->label('Comentariu')
                    ->rows(6)
                    ->disabled(),

                Toggle::make('is_approved')
                    ->label('Recenzie aprobată'),

            ]);
    }
}