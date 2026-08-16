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

                TextColumn::make('order_number')
                    ->label('Comandă')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('first_name')
                    ->label('Client')
                    ->formatStateUsing(
                        fn ($record) =>
                            $record->first_name . ' ' . $record->last_name
                    )
                    ->searchable(),

                TextColumn::make('email')
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('Telefon'),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('RON')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'primary' => 'new',
                        'success' => 'confirmed',
                        'warning' => 'processing',
                        'info' => 'shipped',
                        'gray' => 'delivered',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'new' => 'Nouă',
                        'confirmed' => 'Confirmată',
                        'processing' => 'În pregătire',
                        'shipped' => 'Expediată',
                        'delivered' => 'Livrată',
                        'cancelled' => 'Anulată',
                        default => $state,
                    }),

                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

            ])

            ->filters([

            ])

            ->recordActions([

                ViewAction::make(),

                EditAction::make(),

                /*
                 * WhatsApp
                 */
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(function ($record) {

                        /*
                         * Curățăm numărul de telefon.
                         */
                        $phone = preg_replace(
                            '/\D/',
                            '',
                            $record->phone
                        );

                        /*
                         * Dacă numărul începe cu 0,
                         * îl transformăm în format internațional România.
                         *
                         * Exemplu:
                         * 0750444672
                         * devine:
                         * 40750444672
                         */
                        if (str_starts_with($phone, '0')) {
                            $phone = '40' . substr($phone, 1);
                        }

                        /*
                         * Mesajul WhatsApp.
                         *
                         * Afișăm doar totalul final.
                         * Transportul nu este afișat separat.
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
                 * Factură
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