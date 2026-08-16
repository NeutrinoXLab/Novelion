<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('category.name')
                    ->label('Category'),

                TextEntry::make('brand.name')
                    ->label('Brand')
                    ->placeholder('-'),

                TextEntry::make('name'),

                TextEntry::make('slug'),

                TextEntry::make('sku')
                    ->label('SKU'),

                TextEntry::make('ean')
                    ->placeholder('-'),

                TextEntry::make('supplier_reference')
                    ->placeholder('-'),

                TextEntry::make('purchase_price')
                    ->money(),

                TextEntry::make('selling_price')
                    ->money(),

                TextEntry::make('sale_price')
                    ->money()
                    ->placeholder('-'),

                TextEntry::make('vat_rate')
                    ->numeric(),

                TextEntry::make('stock_quantity')
                    ->numeric(),

                TextEntry::make('low_stock_threshold')
                    ->numeric(),

                TextEntry::make('weight')
                    ->numeric()
                    ->placeholder('-'),

                TextEntry::make('short_description')
                    ->placeholder('-')
                    ->columnSpanFull(),

                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),

                ImageEntry::make('main_image_path')
                    ->placeholder('-'),

                IconEntry::make('is_active')
                    ->boolean(),

                IconEntry::make('is_featured')
                    ->boolean(),

                IconEntry::make('is_new')
                    ->boolean(),

                IconEntry::make('is_on_sale')
                    ->boolean(),

                TextEntry::make('seo_title')
                    ->placeholder('-'),

                TextEntry::make('seo_description')
                    ->placeholder('-')
                    ->columnSpanFull(),

                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),

                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}