<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockProducts extends BaseWidget
{
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Produk Hampir Habis')
            ->description('Produk aktif dengan stok < 10, diurutkan dari terendah')
            ->query(
                Product::query()
                    ->where('is_active', true)
                    ->where('stok', '<', 10)
                    ->limit(10)
            )
            ->defaultSort('stok', 'asc')
            ->extraAttributes([
                'class' => 'fi-fixed-table',
            ])
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->weight('bold')
                    ->color('primary')
                    ->searchable(),

                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama Produk')
                    ->searchable(),

                Tables\Columns\TextColumn::make('stok')
                    ->label('Stok Saat Ini')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'danger',
                        $state <= 5  => 'danger',
                        $state <= 10 => 'warning',
                        default      => 'success',
                    })
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
