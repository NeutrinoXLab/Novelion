<?php

namespace App\Filament\Resources\NewsletterCampaigns\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class NewsletterCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subject')
                    ->label('Subiect')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Ex: Noutăți și oferte speciale Novelion'),

                TextInput::make('title')
                    ->label('Titlu')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Ex: Descoperă noutățile Novelion'),

                Textarea::make('content')
                    ->label('Conținut')
                    ->required()
                    ->rows(12)
                    ->columnSpanFull()
                    ->placeholder('Scrie aici conținutul newsletterului...'),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Ciornă',
                        'sending' => 'În trimitere',
                        'sent' => 'Trimis',
                        'failed' => 'Eșuat',
                    ])
                    ->default('draft')
                    ->required(),

                TextInput::make('recipients_count')
                    ->label('Număr destinatari')
                    ->numeric()
                    ->default(0)
                    ->disabled(),
            ]);
    }
}
