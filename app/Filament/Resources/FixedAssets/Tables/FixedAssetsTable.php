<?php

namespace App\Filament\Resources\FixedAssets\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FixedAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('purchase_date', 'desc')
            ->columns([
                TextColumn::make('name')->label('NAMA ASET')->searchable()->sortable(),
                TextColumn::make('purchase_date')->label('DIBELI')->date('d M Y')->sortable(),
                TextColumn::make('acquisition_cost')
                    ->label('HARGA PEROLEHAN')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                TextColumn::make('useful_life_months')->label('MASA MANFAAT')->suffix(' bulan')->sortable(),
                IconColumn::make('is_active')->label('AKTIF')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
