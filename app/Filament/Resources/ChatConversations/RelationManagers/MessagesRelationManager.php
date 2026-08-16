<?php

namespace App\Filament\Resources\ChatConversations\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('sender_type')
                    ->label('Expeditor')
                    ->options([
                        'customer' => 'Client',
                        'admin' => 'Administrator',
                    ])
                    ->default('admin')
                    ->required(),

                Textarea::make('message')
                    ->label('Mesaj')
                    ->placeholder('Scrie răspunsul pentru client...')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),

                DateTimePicker::make('read_at')
                    ->label('Citit la'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')

            ->columns([

                TextColumn::make('sender_type')
                    ->label('Expeditor')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'customer' => 'Client',
                        'admin' => 'Administrator',
                        default => $state,
                    })
                    ->badge(),

                TextColumn::make('message')
                    ->label('Mesaj')
                    ->limit(80)
                    ->wrap(),

                TextColumn::make('read_at')
                    ->label('Citit la')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Trimis la')
                    ->dateTime()
                    ->sortable(),

            ])

            ->filters([
                //
            ])

            ->headerActions([

                CreateAction::make()
                    ->label('Răspunde clientului')
                    ->icon('heroicon-o-paper-airplane'),

                AssociateAction::make(),

            ])

            ->recordActions([

                EditAction::make(),

                DissociateAction::make(),

                DeleteAction::make(),

            ])

            ->toolbarActions([

                BulkActionGroup::make([

                    DissociateBulkAction::make(),

                    DeleteBulkAction::make(),

                ]),

            ]);
    }
}