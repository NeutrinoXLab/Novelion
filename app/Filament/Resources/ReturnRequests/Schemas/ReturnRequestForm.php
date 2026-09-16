<?php

namespace App\Filament\Resources\ReturnRequests\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ReturnRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informații retur')
                    ->schema([
                        Placeholder::make('order_number')
                            ->label('Comandă')
                            ->content(fn ($record) => $record?->order?->order_number ?? '—'),

                        Placeholder::make('customer')
                            ->label('Client')
                            ->content(fn ($record) => $record?->user?->name ?? '—'),

                        Placeholder::make('reason_display')
                            ->label('Motiv')
                            ->content(fn ($record) => $record?->reason ?? '—'),

                        Placeholder::make('type_display')->label('Flux')
                            ->content(fn ($record) => $record?->type === 'nonconformity' ? 'Produs defect/neconform' : 'Retragere'),
                        Placeholder::make('items_display')->label('Produse și cantități')
                            ->content(fn ($record) => $record?->items->map(fn ($item) => $item->orderItem?->product_name.' × '.$item->quantity)->implode(', ') ?: 'Retur anterior fără poziții'),
                        Placeholder::make('refund_amount_display')->label('Sumă estimată')
                            ->content(fn ($record) => $record?->refund_amount === null ? '—' : $record->refund_amount.' RON'),
                        Placeholder::make('refund_status_display')->label('Stare rambursare')
                            ->content(fn ($record) => $record?->refund_status ?? 'Neinițiată'),
                        Placeholder::make('bank_iban_display')->label('IBAN comunicat')
                            ->content(fn ($record) => $record?->bank_iban ?? '—'),

                        Placeholder::make('photos_display')->label('Fotografii opționale')
                            ->content(fn ($record) => new HtmlString($record?->photos->map(fn ($photo) => '<a href="'.e(route('returns.photos.download', $photo)).'" class="underline">Fotografie #'.$photo->id.'</a>')->implode('<br>') ?: 'Fără fotografii')),

                        Placeholder::make('notes_display')
                            ->label('Observații')
                            ->content(fn ($record) => $record?->notes ?: 'Fără observații'),

                        Placeholder::make('requested_at_display')
                            ->label('Solicitat la')
                            ->content(fn ($record) => $record?->requested_at?->format('d.m.Y H:i') ?? '—'),
                    ])
                    ->columns(2),

                Section::make('Gestionare retur')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(function ($record): array {
                                return match ($record?->status) {
                                    'requested' => [
                                        'requested' => 'Solicitat',
                                        'approved' => 'Aprobat',
                                        'rejected' => 'Respins',
                                    ],

                                    'approved' => [
                                        'approved' => 'Aprobat',
                                        'received' => 'Primit',
                                    ],

                                    'received' => [
                                        'received' => 'Primit',
                                    ],

                                    'refunded' => [
                                        'refunded' => 'Rambursat',
                                    ],

                                    'rejected' => [
                                        'rejected' => 'Respins',
                                    ],

                                    default => [],
                                };
                            })
                            ->required(),

                        DateTimePicker::make('approved_at')
                            ->label('Aprobat la')
                            ->disabled(),

                        DateTimePicker::make('received_at')
                            ->label('Primit la')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }
}
