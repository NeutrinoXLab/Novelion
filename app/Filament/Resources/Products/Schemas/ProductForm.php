<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('📦 Informații generale')
                    ->schema([

                        Select::make('category_id')
                            ->label('Categorie')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('brand_id')
                            ->label('Brand')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload(),

                        TextInput::make('name')
                            ->label('Nume produs')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get, ?Product $record) {
                                if (! $record && blank($get('slug'))) {
                                    $set('slug', Product::uniqueSlug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Adresă URL (generată automat)')
                            ->helperText('Este generată automat la creare și rămâne stabilă când editezi numele produsului.')
                            ->readOnly()
                            ->dehydrated()
                            ->maxLength(255),

                        TextInput::make('sku')
                            ->label('SKU')
                            ->required(),

                        TextInput::make('ean')
                            ->label('EAN'),

                        TextInput::make('supplier_reference')
                            ->label('Cod furnizor'),

                    ])
                    ->columns(2),

                Section::make('💰 Prețuri')
                    ->schema([

                        TextInput::make('purchase_price')
                            ->label('Preț achiziție')
                            ->required()
                            ->numeric()
                            ->prefix('Lei'),

                        TextInput::make('selling_price')
                            ->label('Preț vânzare')
                            ->required()
                            ->numeric()
                            ->prefix('Lei'),

                        TextInput::make('sale_price')
                            ->label('Preț promoțional')
                            ->numeric()
                            ->prefix('Lei'),

                        TextInput::make('vat_rate')->label('TVA (%)')->numeric()->default(0)
                            ->helperText('Novelion este în prezent neplătitoare de TVA.'),

                    ])
                    ->columns(2),

                Section::make('📦 Stoc și transport')
                    ->description('Datele de greutate și dimensiuni vor fi folosite pentru calcularea automată a transportului.')
                    ->schema([

                        TextInput::make('stock_quantity')
                            ->label('Stoc')
                            ->required()
                            ->numeric()
                            ->default(0),

                        TextInput::make('low_stock_threshold')
                            ->label('Prag stoc minim')
                            ->required()
                            ->numeric()
                            ->default(5),

                        TextInput::make('weight')
                            ->label('Greutate (kg)')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(fn ($get) => (bool) $get('is_active'))
                            ->step(0.01)
                            ->suffix('kg'),

                        TextInput::make('length')
                            ->label('Lungime (cm)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('cm'),

                        TextInput::make('width')
                            ->label('Lățime (cm)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('cm'),

                        TextInput::make('height')
                            ->label('Înălțime (cm)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('cm'),

                    ])
                    ->columns(3),

                Section::make('📝 Descriere')
                    ->schema([

                        Textarea::make('short_description')
                            ->label('Descriere scurtă')
                            ->rows(3),

                        Textarea::make('description')
                            ->label('Descriere completă')
                            ->rows(8),

                    ]),

                Section::make('Siguranță, identificare și garanție comercială')
                    ->description('Completează numai informații reale. Garanția comercială a producătorului este separată de garanția legală.')
                    ->schema([
                        TextInput::make('manufacturer_name')->label('Producător')->required(fn ($get) => (bool) $get('is_active')),
                        Textarea::make('manufacturer_contact')->label('Contact producător')->required(fn ($get) => (bool) $get('is_active')),
                        TextInput::make('model_identifier')->label('Marcă/model/identificare')->required(fn ($get) => (bool) $get('is_active')),
                        Toggle::make('requires_eu_responsible_person')->label('Necesită persoană responsabilă în UE')->live(),
                        TextInput::make('eu_responsible_person_name')->label('Persoană responsabilă în UE')->required(fn ($get) => (bool) $get('is_active') && (bool) $get('requires_eu_responsible_person')),
                        Textarea::make('eu_responsible_person_contact')->label('Contact persoană responsabilă')->required(fn ($get) => (bool) $get('is_active') && (bool) $get('requires_eu_responsible_person')),
                        Textarea::make('warnings')->label('Avertismente'),
                        Textarea::make('safety_instructions')->label('Informații/instrucțiuni de siguranță'),
                        Textarea::make('commercial_warranty')->label('Garanție comercială a producătorului (dacă există)'),
                    ])->columns(2),

                Section::make('⚙️ SEO')
                    ->schema([

                        TextInput::make('seo_title')
                            ->label('Titlu SEO'),

                        Textarea::make('seo_description')
                            ->label('Descriere SEO')
                            ->rows(3),

                    ]),

                Section::make('✅ Opțiuni')
                    ->schema([

                        Toggle::make('is_active')
                            ->label('Activ')
                            ->default(false),

                        Toggle::make('is_featured')
                            ->label('Recomandat')
                            ->default(false),

                        Toggle::make('is_new')
                            ->label('Produs nou')
                            ->default(true),

                        Toggle::make('is_on_sale')
                            ->label('În promoție')
                            ->default(false),

                    ])
                    ->columns(4),

            ]);
    }
}
