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
                Section::make('Stock Opname Details')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reference_number'),
                        TextEntry::make('date')
                            ->date(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'posted' => 'success',
                                default => 'primary',
                            }),
                        TextEntry::make('notes')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Items')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                TextEntry::make('product.nama')
                                    ->label('Product')
                                    ->formatStateUsing(fn ($state, $record) => $record->product?->sku ? "{$state} ({$record->product->sku})" : $state),
                                TextEntry::make('system_qty')
                                    ->label('System Qty')
                                    ->formatStateUsing(fn ($state) => "{$state} pcs"),
                                TextEntry::make('physical_qty')
                                    ->label('Physical Qty')
                                    ->weight('bold')
                                    ->formatStateUsing(fn ($state) => "{$state} pcs"),
                                TextEntry::make('variance')
                                    ->label('Variance')
                                    ->badge()
                                    ->color(fn (int $state): string => match (true) {
                                        $state > 0 => 'success',
                                        $state < 0 => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn ($state) => ($state > 0 ? "+{$state}" : "{$state}").' pcs'),
                                TextEntry::make('unit_cost')
                                    ->label('Cost Satuan')
                                    ->money('IDR'),
                                TextEntry::make('total_value')
                                    ->label('Total Nilai Varians')
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
