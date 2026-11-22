<?php

namespace App\Filament\Resources\StockOpnames\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockOpnameInfolist
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
                        TextEntry::make('reference_number')
                            ->label('Nomor Referensi'),
                        TextEntry::make('date')
                            ->label('Tanggal')
                            ->date(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'posted' => 'success',
                                default => 'primary',
                            }),
                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Daftar Barang')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('Barang')
                            ->schema([
                                TextEntry::make('product.nama')
                                    ->label('Produk')
                                    ->formatStateUsing(fn ($state, $record) => $record->product?->sku ? "{$state} ({$record->product->sku})" : $state),
                                TextEntry::make('system_qty')
                                    ->label('Stok Sistem')
                                    ->formatStateUsing(fn ($state) => "{$state} pcs"),
                                TextEntry::make('physical_qty')
                                    ->label('Stok Fisik')
                                    ->weight('bold')
                                    ->formatStateUsing(fn ($state) => "{$state} pcs"),
                                TextEntry::make('variance')
                                    ->label('Selisih')
                                    ->badge()
                                    ->color(fn (int $state): string => match (true) {
                                        $state > 0 => 'success',
                                        $state < 0 => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn ($state) => ($state > 0 ? "+{$state}" : "{$state}").' pcs'),
                                TextEntry::make('unit_cost')
                                    ->label('Harga Beli Satuan')
                                    ->money('IDR'),
                                TextEntry::make('total_value')
                                    ->label('Total Nilai Selisih')
                                    ->badge()
                                    ->color(fn (float $state): string => match (true) {
                                        $state > 0 => 'success',
                                        $state < 0 => 'danger',
                                        default => 'gray',
                                    })
                                    ->money('IDR'),
                                TextEntry::make('notes')
                                    ->label('Catatan / Alasan')
                                    ->placeholder('-'),
                            ])
                            ->columns(7),
                    ]),
            ]);
    }
}
