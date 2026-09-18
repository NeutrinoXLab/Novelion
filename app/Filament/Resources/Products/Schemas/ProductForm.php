<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Forms\Components\RichEditor;
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
                            ->helperText('Adresa simplificată a paginii produsului. Se generează automat și, de regulă, nu trebuie modificată.')
                            ->readOnly()
                            ->dehydrated()
                            ->maxLength(255),

                        TextInput::make('sku')
                            ->label('SKU')
                            ->helperText('Cod unic intern al produsului. Ajută la identificarea produsului în stoc, comenzi și administrare.')
                            ->required(),

                        TextInput::make('ean')
                            ->label('EAN / GTIN')
                            ->helperText('Codul global de identificare tipărit de obicei sub codul de bare. Completează-l numai dacă produsul are un astfel de cod.'),

                        TextInput::make('supplier_reference')
                            ->label('Cod furnizor')
                            ->helperText('Codul folosit de furnizor pentru acest produs. Util la reaprovizionare și la identificarea produsului pe documentele furnizorului.'),

                    ])
                    ->columns(2),

                Section::make('💰 Prețuri')
                    ->schema([

                        TextInput::make('purchase_price')
                            ->label('Preț achiziție')
                            ->helperText('Costul de cumpărare al produsului, folosit intern pentru evidență și calculul marjei.')
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
                            ->helperText('Prețul de vânzare redus, folosit atunci când produsul este oferit la promoție.')
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
                            ->helperText('Cantitatea la care produsul este semnalat ca având stoc redus, pentru avertizare și reaprovizionare.')
                            ->required()
                            ->numeric()
                            ->default(5),

                        TextInput::make('weight')
                            ->label('Greutate (kg)')
                            ->helperText('Greutatea unei bucăți, în kilograme. Este folosită la stabilirea opțiunilor și costului de transport.')
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

                        RichEditor::make('description')
                            ->label('Descriere completă')
                            ->helperText('Formatează descrierea cu titluri, text evidențiat și liste. Conținutul este afișat pe toată lățimea paginii produsului.')
                            ->toolbarButtons([
                                ['bold', 'italic', 'textColor'],
                                ['h2', 'h3'],
                                ['bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ]),

                    ]),

                Section::make('Siguranță, identificare și garanție comercială')
                    ->description('Completează numai informații reale. Garanția comercială a producătorului este separată de garanția legală.')
                    ->schema([
                        TextInput::make('manufacturer_name')->label('Producător')->helperText('Denumirea persoanei sau companiei care a fabricat produsul. Introdu numai informația reală de pe produs ori documentele sale.')->required(fn ($get) => (bool) $get('is_active')),
                        Textarea::make('manufacturer_contact')->label('Date de contact producător')->helperText('Adresa poștală și, dacă sunt disponibile, adresa electronică sau alte date oficiale de contact ale producătorului.')->required(fn ($get) => (bool) $get('is_active')),
                        TextInput::make('model_identifier')->label('Marcă/model/identificare')->helperText('Identificatorul prin care produsul poate fi recunoscut, precum marca, modelul sau seria.')->required(fn ($get) => (bool) $get('is_active')),
                        Toggle::make('requires_eu_responsible_person')->label('Necesită persoană responsabilă în UE')->helperText('Activează dacă produsul are o persoană responsabilă în Uniunea Europeană ale cărei date trebuie afișate.')->live(),
                        TextInput::make('eu_responsible_person_name')->label('Persoană responsabilă în UE')->helperText('Numele persoanei sau companiei responsabile în UE, când este relevant pentru acest produs.')->required(fn ($get) => (bool) $get('is_active') && (bool) $get('requires_eu_responsible_person')),
                        Textarea::make('eu_responsible_person_contact')->label('Date de contact persoană responsabilă')->helperText('Datele oficiale de contact ale persoanei responsabile în UE. Nu completa date presupuse sau fictive.')->required(fn ($get) => (bool) $get('is_active') && (bool) $get('requires_eu_responsible_person')),
                        Textarea::make('warnings')->label('Avertismente'),
                        Textarea::make('safety_instructions')->label('Informații/instrucțiuni de siguranță'),
                        Textarea::make('commercial_warranty')->label('Garanție comercială a producătorului (dacă există)'),
                    ])->columns(2),

                Section::make('⚙️ SEO')
                    ->schema([

                        TextInput::make('seo_title')
                            ->label('Titlu SEO')
                            ->helperText('Titlul folosit de motoarele de căutare pentru pagina produsului. Este recomandat să descrie clar produsul.'),

                        Textarea::make('seo_description')
                            ->label('Descriere SEO')
                            ->helperText('Scurt rezumat al produsului pentru motoarele de căutare. Poate apărea sub titlul paginii în rezultatele căutării.')
                            ->rows(3),

                    ]),

                Section::make('✅ Opțiuni')
                    ->schema([

                        Toggle::make('is_active')
                            ->label('Activ')
                            ->helperText('Face produsul disponibil în magazin. Produsele inactive rămân în administrare.')
                            ->default(false),

                        Toggle::make('is_featured')
                            ->label('Recomandat')
                            ->helperText('Afișează insigna „Recomandat” pe pagina produsului.')
                            ->default(false),

                        Toggle::make('is_new')
                            ->label('Produs nou')
                            ->helperText('Afișează insigna „Nou” și include produsul în lista de produse noi.')
                            ->default(true),

                        Toggle::make('is_on_sale')
                            ->label('În promoție')
                            ->helperText('Marchează intern produsul ca fiind în promoție. Reducerea afișată depinde de existența unui preț promoțional valid.')
                            ->default(false),

                    ])
                    ->columns(4),

            ]);
    }
}
