<?php

namespace App\Filament\Resources\StockTransfers\Schemas;

use App\Models\AccountingPeriod;
use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StockTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Transfer')
                    ->components([
                        DatePicker::make('tanggal')
                            ->label('Tanggal Transfer')
                            ->default(now())
                            ->required()
                            ->live()
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        if (! $value) {
                                            return;
                                        }
                                        $period = AccountingPeriod::where('start_date', '<=', $value)
                                            ->where('end_date', '>=', $value)
                                            ->first();
                                        if ($period && $period->isClosed()) {
                                            $fail("Periode akuntansi '{$period->label}' sudah ditutup (Tutup Buku). Transaksi transfer stok tidak dapat dicatat pada tanggal ini.");
                                        }
                                    };
                                },
                            ])
                            ->helperText(function ($state) {
                                if (! $state) {
                                    return null;
                                }
                                $period = AccountingPeriod::where('start_date', '<=', $state)
                                    ->where('end_date', '>=', $state)
                                    ->first();
                                if ($period && $period->isClosed()) {
                                    return "⚠️ PERINGATAN: Periode '{$period->label}' telah ditutup. Tidak dapat melakukan transfer pada tanggal ini.";
                                }

                                return null;
                            }),

                        Select::make('source_product_id')
                            ->label('Produk Asal (Sumber)')
                            ->relationship('sourceProduct', 'nama')
                            ->searchable()
                            ->preload()
                            ->live(onBlur: true)
                            ->required()
                            ->helperText(function (Get $get) {
                                if (! $get('source_product_id')) {
                                    return null;
                                }
                                $product = Product::find($get('source_product_id'));

                                return $product ? "Sisa Stok: {$product->stok} (Cost: Rp ".number_format($product->harga_beli, 0, ',', '.').')' : null;
                            }),

                        Select::make('target_product_id')
                            ->label('Produk Tujuan')
                            ->relationship('targetProduct', 'nama')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->different('source_product_id')
                            ->helperText(function (Get $get) {
                                if (! $get('target_product_id')) {
                                    return null;
                                }
                                $product = Product::find($get('target_product_id'));

                                return $product ? "Sisa Stok: {$product->stok} (Cost: Rp ".number_format($product->harga_beli, 0, ',', '.').')' : null;
                            }),

                        TextInput::make('quantity')
                            ->label('Jumlah Transfer')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(function (Get $get) {
                                if (! $get('source_product_id')) {
                                    return null;
                                }
                                $product = Product::find($get('source_product_id'));

                                return $product ? $product->stok : null;
                            }),

                        Textarea::make('keterangan')
                            ->label('Keterangan / Alasan Transfer')
                            ->nullable()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
