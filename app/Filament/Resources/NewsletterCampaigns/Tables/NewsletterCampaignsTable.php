<?php

namespace App\Filament\Resources\NewsletterCampaigns\Tables;

use App\Services\NewsletterService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NewsletterCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')
                    ->searchable(),

                TextColumn::make('title')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('recipients_count')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('sendTest')
                    ->label('Trimite test')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->form([
                        TextInput::make('email')
                            ->label('Adresa de email')
                            ->email()
                            ->required()
                            ->placeholder('exemplu@email.com'),
                    ])
                    ->action(function ($record, array $data): void {
                        app(NewsletterService::class)->sendTest(
                            $record,
                            $data['email']
                        );
                    })
                    ->successNotificationTitle(
                        'Emailul de test a fost trimis cu succes.'
                    ),

                Action::make('sendCampaign')
                    ->label('Trimite newsletter')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Trimite newsletterul?')
                    ->modalDescription(
                        'Newsletterul va fi pus in Queue pentru toti abonatii activi.'
                    )
                    ->modalSubmitActionLabel(
                        'Da, trimite newsletterul'
                    )
                    ->visible(function ($record): bool {
                        return $record->status === 'draft';
                    })
                    ->action(function ($record): void {
                        app(NewsletterService::class)->queueCampaign($record);
                    })
                    ->successNotificationTitle(
                        'Newsletterul a fost pus in Queue.'
                    ),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
