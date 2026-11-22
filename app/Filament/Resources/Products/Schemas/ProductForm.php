<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
                    ->helperText('Dibuat otomatis berdasarkan kategori dan nama produk.')
                    ->placeholder('Otomatis dibuat saat disimpan')
                    ->visibleOn('edit'),

                TextInput::make('nama')
                    ->label('Nama Produk')
                    ->placeholder('Masukkan nama produk')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        $set('nama', Str::title($state));
                    })
                    ->dehydrateStateUsing(fn ($state) => Str::title($state)),

                Select::make('kategori')
                    ->label('Kategori')
                    ->options([
                        'bunga' => '🌸 Bunga',
                        'packaging' => '📦 Kemasan',
                        'others' => '🔖 Lainnya',
                    ])
                    ->default('bunga')
                    ->required()
                    ->native(false),

                Section::make('Harga')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('harga_beli')
                            ->label('Harga Beli (Modal)')
                            ->placeholder('Masukkan harga beli')
                            ->required()
                            ->prefix('Rp')
                            ->maxLength(13)
                            ->extraInputAttributes([
                                'inputmode' => 'numeric',
                                'oninput' => "this.value=this.value.replace(/[^0-9]/g,'').replace(/^0+(?=\\d)/,'');let v=this.value;this.value=v.replace(/\\B(?=(\\d{3})+(?!\\d))/g,'.');",
                            ])
                            ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) ($state ?? 0)))
                            ->formatStateUsing(fn ($state) => $state ? number_format((int) $state, 0, ',', '.') : '')
                            ->disabled(fn () => ! Filament::auth()->user()?->is_super_admin)
                            ->dehydrated(),

                        Grid::make(3)->schema([
                            TextInput::make('harga_jual')
                                ->label('Harga Jual (Toko / Default)')
                                ->placeholder('Masukkan harga toko')
                                ->required()
                                ->prefix('Rp')
                                ->maxLength(13)
                                ->extraInputAttributes([
                                    'inputmode' => 'numeric',
                                    'oninput' => "this.value=this.value.replace(/[^0-9]/g,'').replace(/^0+(?=\\d)/,'');let v=this.value;this.value=v.replace(/\\B(?=(\\d{3})+(?!\\d))/g,'.');",
                                ])
                                ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) ($state ?? 0)))
                                ->formatStateUsing(fn ($state) => $state ? number_format((int) $state, 0, ',', '.') : '')
                                ->disabled(fn () => ! Filament::auth()->user()?->is_super_admin)
                                ->dehydrated(),

                            TextInput::make('harga_vendor')
                                ->label('Harga Jual (Vendor)')
                                ->placeholder('Masukkan harga vendor')
                                ->required()
                                ->default(0)
                                ->prefix('Rp')
                                ->maxLength(13)
                                ->extraInputAttributes([
                                    'inputmode' => 'numeric',
                                    'oninput' => "this.value=this.value.replace(/[^0-9]/g,'').replace(/^0+(?=\\d)/,'');let v=this.value;this.value=v.replace(/\\B(?=(\\d{3})+(?!\\d))/g,'.');",
                                ])
                                ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) ($state ?? 0)))
                                ->formatStateUsing(fn ($state) => $state ? number_format((int) $state, 0, ',', '.') : '')
                                ->disabled(fn () => ! Filament::auth()->user()?->is_super_admin)
                                ->dehydrated(),

                            TextInput::make('harga_dekor')
                                ->label('Harga Jual (Dekor)')
                                ->placeholder('Masukkan harga dekor')
                                ->required()
                                ->default(0)
                                ->prefix('Rp')
                                ->maxLength(13)
                                ->extraInputAttributes([
                                    'inputmode' => 'numeric',
                                    'oninput' => "this.value=this.value.replace(/[^0-9]/g,'').replace(/^0+(?=\\d)/,'');let v=this.value;this.value=v.replace(/\\B(?=(\\d{3})+(?!\\d))/g,'.');",
                                ])
                                ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) ($state ?? 0)))
                                ->formatStateUsing(fn ($state) => $state ? number_format((int) $state, 0, ',', '.') : '')
                                ->disabled(fn () => ! Filament::auth()->user()?->is_super_admin)
                                ->dehydrated(),
                        ]),
                    ]),

                TextInput::make('stok')
                    ->label('Stok')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Stok dikelola melalui Pergerakan Stok.'),
            ]);
    }
}
