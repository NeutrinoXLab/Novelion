<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextEntry::make('order_number')
                    ->label('Număr comandă'),

                TextEntry::make('customer_type')
                    ->label('Tip client')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'company' => 'Persoană juridică',
                        default => 'Persoană fizică',
                    }),

                TextEntry::make('first_name')
                    ->label('Prenume'),

                TextEntry::make('last_name')
                    ->label('Nume'),

                TextEntry::make('email')
                    ->label('Email'),

                TextEntry::make('phone')
                    ->label('Telefon'),

                TextEntry::make('county')
                    ->label('Județ'),

                TextEntry::make('city')
                    ->label('Oraș'),

                TextEntry::make('address')
                    ->label('Adresă'),

                TextEntry::make('postal_code')
                    ->label('Cod poștal')
                    ->placeholder('-'),

                TextEntry::make('company_name')
                    ->label('Denumire firmă')
                    ->placeholder('-'),

                TextEntry::make('company_vat')
                    ->label('CUI')
                    ->placeholder('-'),

                TextEntry::make('company_registration')
                    ->label('Nr. Registrul Comerțului')
                    ->placeholder('-'),

                TextEntry::make('company_address')
                    ->label('Adresă firmă')
                    ->placeholder('-'),

                TextEntry::make('company_city')
                    ->label('Oraș firmă')
                    ->placeholder('-'),

                TextEntry::make('company_county')
                    ->label('Județ firmă')
                    ->placeholder('-'),

                TextEntry::make('shipping_first_name')
                    ->label('Prenume livrare')
                    ->placeholder('-'),

                TextEntry::make('shipping_last_name')
                    ->label('Nume livrare')
                    ->placeholder('-'),

                TextEntry::make('shipping_phone')
                    ->label('Telefon livrare')
                    ->placeholder('-'),

                TextEntry::make('shipping_county')
                    ->label('Județ livrare')
                    ->placeholder('-'),

                TextEntry::make('shipping_city')
                    ->label('Oraș livrare')
                    ->placeholder('-'),

                TextEntry::make('shipping_address')
                    ->label('Adresă livrare')
                    ->placeholder('-'),

                TextEntry::make('shipping_postal_code')
                    ->label('Cod poștal livrare')
                    ->placeholder('-'),

                TextEntry::make('subtotal')
                    ->label('Subtotal')
                    ->money('RON'),

                TextEntry::make('shipping_cost')
                    ->label('Transport')
                    ->money('RON'),

                TextEntry::make('total')
                    ->label('Total')
                    ->money('RON'),

                TextEntry::make('payment_method')
                    ->label('Metodă plată')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash' => 'Ramburs',
                        'stripe' => 'Card bancar',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'warning',
                        'stripe' => 'success',
                        default => 'gray',
                    }),

                TextEntry::make('payment_status')
                    ->label('Status plată')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'În așteptare',
                        'paid' => 'Plătită',
                        'failed' => 'Eșuată',
                        'refunded' => 'Rambursată',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),

                TextEntry::make('courier')
                    ->label('Curier')
                    ->placeholder('-'),

                TextEntry::make('awb_number')
                    ->label('AWB')
                    ->placeholder('-'),

                TextEntry::make('tracking_url')
                    ->label('Tracking')
                    ->url(fn (?string $state) => $state)
                    ->openUrlInNewTab()
                    ->placeholder('-'),

                TextEntry::make('shipped_at')
                    ->label('Expediată la')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),

                TextEntry::make('status')
                    ->label('Status comandă')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'În așteptare',
                        'processing' => 'În procesare',
                        'shipped' => 'Expediată',
                        'delivered' => 'Livrată',
                        'cancelled' => 'Anulată',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextEntry::make('notes')
                    ->label('Observații')
                    ->placeholder('-')
                    ->columnSpanFull(),

                RepeatableEntry::make('items')
                    ->label('Produse comandate')
                    ->schema([

                        TextEntry::make('product_name')
                            ->label('Produs')
                            ->weight('bold')
                            ->columnSpan(2),

                        TextEntry::make('product.sku')
                            ->label('SKU')
                            ->placeholder('-'),

                        TextEntry::make('quantity')
                            ->label('Cantitate')
                            ->badge(),

                        TextEntry::make('price')
                            ->label('Preț unitar')
                            ->money('RON')
                            ->color('gray'),

                        TextEntry::make('total')
                            ->label('Total')
                            ->money('RON')
                            ->color('success')
                            ->weight('bold'),

                    ])
                    ->columns(6)
                    ->columnSpanFull(),

                TextEntry::make('created_at')
                    ->label('Creată la')
                    ->dateTime('d.m.Y H:i'),

                TextEntry::make('updated_at')
                    ->label('Actualizată la')
                    ->dateTime('d.m.Y H:i'),

            ]);
    }
}