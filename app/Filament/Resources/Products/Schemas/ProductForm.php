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
                    ->prefix('Rp')
                    ->maxLength(13)
                    ->extraInputAttributes([
                        'inputmode' => 'numeric',
                        'oninput' => "this.value=this.value.replace(/[^0-9]/g,'').replace(/^0+(?=\\d)/,'');let v=this.value;this.value=v.replace(/\\B(?=(\\d{3})+(?!\\d))/g,'.');",
                    ])
                    ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) ($state ?? 0)))
                    ->formatStateUsing(fn ($state) => $state ? number_format((int) $state, 0, ',', '.') : '')
                    ->disabled(fn () => !Filament::auth()->user()?->is_super_admin)
                    ->dehydrated(),
                TextInput::make('harga_jual_barang')
                    ->label('Selling Price')
                    ->placeholder('Enter selling price')
                    ->required()
                    ->prefix('Rp')
                    ->maxLength(13)
                    ->extraInputAttributes([
                        'inputmode' => 'numeric',
                        'oninput' => "this.value=this.value.replace(/[^0-9]/g,'').replace(/^0+(?=\\d)/,'');let v=this.value;this.value=v.replace(/\\B(?=(\\d{3})+(?!\\d))/g,'.');",
                    ])
                    ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) ($state ?? 0)))
                    ->formatStateUsing(fn ($state) => $state ? number_format((int) $state, 0, ',', '.') : '')
                    ->disabled(fn () => !Filament::auth()->user()?->is_super_admin)
                    ->dehydrated(),
                TextInput::make('stok_barang')
                    ->label('Stock')
                    ->placeholder('Enter stock quantity')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->maxLength(6)
                    ->extraInputAttributes([
                        'inputmode' => 'numeric',
                        'min' => 0,
                        'oninput' => "this.value=this.value.replace(/[^0-9]/g,'').replace(/^0+(?=\\d)/,'');this.value=this.value.slice(0,6);",
                    ]),
            ]);
    }
}
