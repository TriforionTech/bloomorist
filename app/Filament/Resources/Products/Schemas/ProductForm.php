<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_barang')
                    ->label('Product Name')
                    ->placeholder('Enter product name')
                    ->required(),
                TextInput::make('harga_beli_barang')
                    ->label('Purchase Price')
                    ->placeholder('Enter purchase price')
                    ->required()
                    ->numeric()
                    ->disabled(fn () => !Filament::auth()->user()?->is_super_admin)
                    ->dehydrated(),
                TextInput::make('harga_jual_barang')
                    ->label('Selling Price')
                    ->placeholder('Enter selling price')
                    ->required()
                    ->numeric()
                    ->disabled(fn () => !Filament::auth()->user()?->is_super_admin)
                    ->dehydrated(),
                TextInput::make('stok_barang')
                    ->label('Stock')
                    ->placeholder('Enter stock quantity')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
