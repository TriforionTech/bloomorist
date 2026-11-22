<?php

namespace App\Filament\Resources\FixedAssets\Tables;

use App\Models\FixedAsset;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FixedAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('purchase_date', 'desc')
            ->columns([
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),

                TextColumn::make('name')
                    ->label('NAMA ASET')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('purchase_date')
                    ->label('TANGGAL BELI')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('acquisition_cost')
                    ->label('HARGA PEROLEHAN')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('useful_life_months')
                    ->label('MASA MANFAAT')
                    ->suffix(' bulan')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('monthly_depreciation')
                    ->label('PENYUSUTAN/BLN')
                    ->state(fn (FixedAsset $record) => round((float) $record->acquisition_cost / max(1, (int) $record->useful_life_months), 2))
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->alignEnd(),

                TextColumn::make('is_active')
                    ->label('STATUS')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state) => $state ? 'success' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status Aset')
                    ->options([
                        '1' => 'Aktif',
                        '0' => 'Nonaktif',
                    ]),
            ])
            ->recordActionsColumnLabel('AKSI')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
