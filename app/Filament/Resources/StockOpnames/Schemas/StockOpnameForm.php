<?php

namespace App\Filament\Resources\StockOpnames\Schemas;

use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class StockOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Detail Stock Opname')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextInput::make('reference_number')
                            ->label('Nomor Referensi')
                            ->required()
                            ->maxLength(255)
                            ->default(fn () => 'SO-'.date('Y-m-d').'-'.Str::random(4))
                            ->readOnly(),
                        DatePicker::make('date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),
                        TextInput::make('notes')
                            ->label('Catatan')
                            ->placeholder('Catatan (opsional)'),
                    ]),

                Section::make('Daftar Barang')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->label('Barang')
                            ->relationship('items')
                            ->columns(7)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Produk')
                                    ->options(Product::where('is_active', true)->pluck('nama', 'id'))
                                    ->required()
                                    ->live()
                                    ->searchable()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('system_qty', $product->stok);
                                                $set('unit_cost', $product->harga_beli);

                                                $physical = (int) ($get('physical_qty') ?: 0);
                                                $variance = $physical - $product->stok;
                                                $set('variance', $variance);
                                                $set('total_value', $variance * $product->harga_beli);
                                            }
                                        }
                                    })
                                    ->columnSpan(2),
                                TextInput::make('system_qty')
                                    ->label('Stok Sistem')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('physical_qty')
                                    ->label('Stok Fisik')
                                    ->numeric()
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $system = (int) $get('system_qty');
                                        $physical = (int) $state;
                                        $variance = $physical - $system;
                                        $cost = (float) $get('unit_cost');

                                        $set('variance', $variance);
                                        $set('total_value', $variance * $cost);
                                    })
                                    ->columnSpan(1),
                                TextInput::make('variance')
                                    ->label('Selisih')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    ->columnSpan(1),
                                TextInput::make('unit_cost')
                                    ->label('Harga Beli Satuan')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    ->prefix('Rp')
                                    ->columnSpan(1),
                                TextInput::make('total_value')
                                    ->label('Total Nilai Selisih')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    ->prefix('Rp')
                                    ->columnSpan(1),
                                TextInput::make('notes')
                                    ->label('Catatan Item')
                                    ->placeholder('Alasan selisih/kondisi')
                                    ->nullable()
                                    ->columnSpan(1),
                            ])
                            ->columns(8)
                            ->defaultItems(0)
                            ->addActionLabel('Tambah Barang'),
                    ]),
            ]);
    }
}
