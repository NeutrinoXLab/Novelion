<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Users / Utilizatori';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_admin === true;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nume')->required()->maxLength(255),
            TextInput::make('email')->label('Email')->email()->required()->maxLength(255)
                ->unique(ignoreRecord: true),
            Select::make('is_admin')->label('Rol')->options([
                0 => 'Client',
                1 => 'Admin',
            ])->required(),
            TextInput::make('password')->label('Parolă nouă')->password()
                ->autocomplete('new-password')->minLength(12)
                ->helperText('Lasă gol pentru a păstra parola actuală.')
                ->dehydrated(fn (?string $state): bool => filled($state)),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label('Nume'),
            TextEntry::make('email')->label('Email'),
            TextEntry::make('is_admin')->label('Rol')
                ->formatStateUsing(fn (bool $state): string => $state ? 'Admin' : 'Client'),
            TextEntry::make('created_at')->label('Creat la')->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nume')->searchable()->sortable(),
            TextColumn::make('email')->label('Email')->searchable()->sortable(),
            TextColumn::make('is_admin')->label('Rol')
                ->formatStateUsing(fn (bool $state): string => $state ? 'Admin' : 'Client'),
            TextColumn::make('created_at')->label('Creat la')->dateTime()->sortable(),
        ])->recordActions([
            ViewAction::make(),
            EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
