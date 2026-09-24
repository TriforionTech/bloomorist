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
                                    ->label('Product'),
                                TextEntry::make('system_qty')
                                    ->label('System Qty'),
                                TextEntry::make('physical_qty')
                                    ->label('Physical Qty'),
                                TextEntry::make('variance')
                                    ->label('Variance'),
                                TextEntry::make('unit_cost')
                                    ->label('Cost')
                                    ->money('IDR'),
                                TextEntry::make('total_value')
                                    ->label('Total Value')
                                    ->money('IDR'),
                            ])
                            ->columns(6),
                    ]),
            ]);
    }
}
