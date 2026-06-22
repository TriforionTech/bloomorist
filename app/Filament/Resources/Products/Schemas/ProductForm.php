<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->label('SKU')
                    ->readOnly()
                    ->helperText('Auto-generated based on category and product name.')
                    ->placeholder('Will be generated on save')
                    ->visibleOn('edit'),

                TextInput::make('nama')
                    ->label('Product Name')
                    ->placeholder('Enter product name')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        $set('nama', Str::title($state));
                    })
                    ->dehydrateStateUsing(fn ($state) => Str::title($state)),

                Select::make('kategori')
                    ->label('Category')
                    ->options([
                        'bunga'     => '🌸 Flowers',
                        'packaging' => '📦 Packaging',
                        'others'    => '🔖 Others',
                    ])
                    ->default('bunga')
                    ->required()
                    ->native(false),

                TextInput::make('harga_beli')
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

                TextInput::make('harga_jual')
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

                TextInput::make('stok')
                    ->label('Stock')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Stock is managed via Stock Movements.'),
            ]);
    }
}
