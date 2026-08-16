<?php

namespace App\Filament\Resources\ShippingRates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShippingRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('🚚 Regulă de transport')
                    ->description(
                        'Configurează limita de greutate, volumul și prețul pentru această categorie de transport.'
                    )
                    ->schema([

                        TextInput::make('name')
                            ->label('Nume')
                            ->placeholder('Ex: S, M, L, XL')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('max_weight')
                            ->label('Greutate maximă')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('kg')
                            ->helperText(
                                'Greutatea maximă pentru această categorie.'
                            ),

                        TextInput::make('max_volume')
                            ->label('Volum maxim')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('cm³')
                            ->helperText(
                                'Volumul maxim al coletului în centimetri cubi.'
                            ),

                        TextInput::make('price')
                            ->label('Preț transport')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('lei')
                            ->required()
                            ->helperText(
                                'Tariful care va fi afișat clientului.'
                            ),

                        TextInput::make('sort_order')
                            ->label('Ordine')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->helperText(
                                'Regulile sunt verificate în această ordine.'
                            ),

                        Toggle::make('is_active')
                            ->label('Regulă activă')
                            ->default(true),

                    ])
                    ->columns(2),

            ]);
    }
}