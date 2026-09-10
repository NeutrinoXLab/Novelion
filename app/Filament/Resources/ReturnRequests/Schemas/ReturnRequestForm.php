<?php

namespace App\Filament\Resources\ReturnRequests\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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