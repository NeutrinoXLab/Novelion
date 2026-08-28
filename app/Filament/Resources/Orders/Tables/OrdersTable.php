<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table

            ->defaultSort('created_at', 'desc')

            ->columns([

                /*
                |--------------------------------------------------------------------------
                | COMANDĂ
                |--------------------------------------------------------------------------
                */

                TextColumn::make('order_number')
                    ->label('Comandă')
                    ->searchable()
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | CLIENT
                |--------------------------------------------------------------------------
                */

                TextColumn::make('first_name')
                    ->label('Client')
                    ->formatStateUsing(
                        fn ($record) =>
                            $record->first_name . ' ' . $record->last_name
                    )
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('Telefon'),

                /*
                |--------------------------------------------------------------------------
                | TOTAL
                |--------------------------------------------------------------------------
                */

                TextColumn::make('total')
                    ->label('Total')
                    ->money('RON')
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | STATUS COMANDĂ
                |--------------------------------------------------------------------------
                */

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'primary' => 'shipped',
                        'success' => 'delivered',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending' => 'În așteptare',
                        'processing' => 'În procesare',
                        'shipped' => 'Expediată',
                        'delivered' => 'Livrată',
                        'cancelled' => 'Anulată',
                        default => $state,
                    }),

                /*
                |--------------------------------------------------------------------------
                | DATA
                |--------------------------------------------------------------------------
                */

                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

            ])

            ->filters([

            ])

            ->recordActions([

                /*
                |--------------------------------------------------------------------------
                | VIZUALIZARE
                |--------------------------------------------------------------------------
                */

                ViewAction::make(),

                /*
                |--------------------------------------------------------------------------
                | EDITARE
                |--------------------------------------------------------------------------
                */

                EditAction::make(),

                /*
                |--------------------------------------------------------------------------
                | WHATSAPP
                |--------------------------------------------------------------------------
                */

                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(function ($record) {

                        /*
                        |--------------------------------------------------------------------------
                        | Curățăm numărul de telefon
                        |--------------------------------------------------------------------------
                        */

                        $phone = preg_replace(
                            '/\D/',
                            '',
                            $record->phone
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | Format internațional România
                        |--------------------------------------------------------------------------
                        |
                        | Exemplu:
                        |
                        | 0722222222
                        |
                        | devine:
                        |
                        | 4072222222
                        |--------------------------------------------------------------------------
                        */

                        if (str_starts_with($phone, '0')) {
                            $phone = '40' . substr($phone, 1);
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Mesaj WhatsApp
                        |--------------------------------------------------------------------------
                        */

                        $message =
                            "Bună ziua, "
                            . $record->first_name
                            . ' '
                            . $record->last_name
                            . "!\n\n"

                            . "Vă contactăm din partea Novelion.\n\n"

                            . "Comanda dumneavoastră "
                            . $record->order_number
                            . " a fost înregistrată cu succes.\n\n"

                            . "Total comandă: "
                            . number_format(
                                $record->total,
                                2,
                                ',',
                                '.'
                            )
                            . " RON\n\n"

                            . "Metoda de plată: "
                            . (
                                $record->payment_method === 'cash'
                                    ? 'Ramburs'
                                    : 'Card'
                            )
                            . "\n\n"

                            . "Vă mulțumim pentru comandă!";

                        return 'https://wa.me/'
                            . $phone
                            . '?text='
                            . urlencode($message);

                    })
                    ->openUrlInNewTab(),

                /*
                |--------------------------------------------------------------------------
                | FACTURĂ
                |--------------------------------------------------------------------------
                */

                Action::make('invoice')
                    ->label('Factură')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (
                        $record,
                        InvoiceService $invoiceService
                    ) {

                        return response()->streamDownload(

                            function () use (
                                $record,
                                $invoiceService
                            ) {

                                echo $invoiceService
                                    ->generate($record)
                                    ->output();

                            },

                            'Factura-'
                            . $record->order_number
                            . '.pdf'

                        );

                    }),

            ])

            ->toolbarActions([

                BulkActionGroup::make([

                    DeleteBulkAction::make(),

                ]),

            ]);
    }
}