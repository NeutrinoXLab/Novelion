<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Comandă')
                    ->schema([

                        TextInput::make('order_number')
                            ->label('Număr comandă')
                            ->disabled(),

                        Select::make('status')
                            ->label('Status comandă')
                            ->options([
                                'pending' => 'În așteptare',
                                'processing' => 'În procesare',
                                'shipped' => 'Expediată',
                                'delivered' => 'Livrată',
                                'cancelled' => 'Anulată',
                            ])
                            ->required(),

                        Select::make('payment_status')
                            ->label('Status plată')
                            ->options([
                                'pending' => 'În așteptare',
                                'paid' => 'Plătită',
                                'failed' => 'Eșuată',
                                'refunded' => 'Rambursată',
                            ])
                            ->disabled(fn ($record) => $record?->payment_method === 'stripe')
                            ->required(),

                        Select::make('payment_method')
                            ->label('Metodă plată')
                            ->options([
                                'cash' => 'Ramburs',
                                'stripe' => 'Card bancar',
                            ])
                            ->disabled(fn ($record) => $record !== null)
                            ->required(),

                    ])
                    ->columns(2),

                Section::make('Date client')
                    ->schema([

                        Select::make('customer_type')
                            ->label('Tip client')
                            ->options([
                                'individual' => 'Persoană fizică',
                                'company' => 'Persoană juridică',
                            ]),

                        Select::make('different_shipping_address')
                            ->label('Adresă de livrare diferită')
                            ->options([
                                0 => 'Nu',
                                1 => 'Da',
                            ]),

                        TextInput::make('first_name')
                            ->label('Prenume'),

                        TextInput::make('last_name')
                            ->label('Nume'),

                        TextInput::make('email')
                            ->label('Email')
                            ->email(),

                        TextInput::make('phone')
                            ->label('Telefon'),

                        TextInput::make('county')
                            ->label('Județ'),

                        TextInput::make('city')
                            ->label('Oraș'),

                        TextInput::make('address')
                            ->label('Adresă')
                            ->columnSpanFull(),

                        TextInput::make('postal_code')
                            ->label('Cod poștal'),

                    ])
                    ->columns(2),

                Section::make('Date firmă')
                    ->schema([

                        TextInput::make('company_name')
                            ->label('Denumire firmă'),

                        TextInput::make('company_vat')
                            ->label('CUI'),

                        TextInput::make('company_registration')
                            ->label('Nr. Registrul Comerțului'),

                        TextInput::make('company_address')
                            ->label('Adresă firmă')
                            ->columnSpanFull(),

                        TextInput::make('company_city')
                            ->label('Oraș firmă'),

                        TextInput::make('company_county')
                            ->label('Județ firmă'),

                    ])
                    ->columns(2),

                Section::make('Adresă de livrare')
                    ->schema([

                        TextInput::make('shipping_first_name')
                            ->label('Prenume'),

                        TextInput::make('shipping_last_name')
                            ->label('Nume'),

                        TextInput::make('shipping_phone')
                            ->label('Telefon'),

                        TextInput::make('shipping_address')
                            ->label('Adresă')
                            ->columnSpanFull(),

                        TextInput::make('shipping_city')
                            ->label('Oraș'),

                        TextInput::make('shipping_county')
                            ->label('Județ'),

                        TextInput::make('shipping_postal_code')
                            ->label('Cod poștal'),

                    ])
                    ->columns(2)
                    ->visible(fn ($get) => (bool) $get('different_shipping_address')),

                Section::make('Observații')
                    ->schema([

                        Textarea::make('notes')
                            ->label('Observații')
                            ->rows(4),

                    ]),

            ]);
    }
}
