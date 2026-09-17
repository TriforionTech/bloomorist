<?php

namespace App\Filament\Resources\StockTransfers;

use App\Filament\Resources\StockTransfers\Pages\ManageStockTransfers;
use App\Models\StockTransfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';

    protected static ?string $modelLabel = 'Stock Transfer';

    protected static ?string $pluralModelLabel = 'Stock Transfers';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Transfer')
                    ->components([
                        \Filament\Forms\Components\DatePicker::make('tanggal')
                            ->label('Tanggal Transfer')
                            ->default(now())
                            ->required(),
                        \Filament\Forms\Components\Select::make('source_product_id')
                            ->label('Produk Asal (Sumber)')
                            ->relationship('sourceProduct', 'nama')
                            ->searchable()
                            ->preload()
                            ->live(onBlur: true)
                            ->required()
                            ->helperText(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                if (! $get('source_product_id')) {
                                    return null;
                                }
                                $product = \App\Models\Product::find($get('source_product_id'));

                                return $product ? "Sisa Stok: {$product->stok} (Cost: Rp ".number_format($product->harga_beli, 0, ',', '.').')' : null;
                            }),
                        \Filament\Forms\Components\Select::make('target_product_id')
                            ->label('Produk Tujuan')
                            ->relationship('targetProduct', 'nama')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->different('source_product_id')
                            ->helperText(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                if (! $get('target_product_id')) {
                                    return null;
                                }
                                $product = \App\Models\Product::find($get('target_product_id'));

                                return $product ? "Sisa Stok: {$product->stok} (Cost: Rp ".number_format($product->harga_beli, 0, ',', '.').')' : null;
                            }),
                        \Filament\Forms\Components\TextInput::make('quantity')
                            ->label('Jumlah Transfer')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                if (! $get('source_product_id')) {
                                    return null;
                                }
                                $product = \App\Models\Product::find($get('source_product_id'));

                                return $product ? $product->stok : null;
                            }),
                        \Filament\Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan / Alasan Transfer')
                            ->nullable()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('sourceProduct.nama')
                    ->label('Dari Produk')
                    ->sortable()
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('targetProduct.nama')
                    ->label('Ke Produk')
                    ->sortable()
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // Edit and Delete are disabled for accounting integrity
            ])
            ->toolbarActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStockTransfers::route('/'),
        ];
    }
}
