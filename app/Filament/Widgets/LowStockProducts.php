<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockProducts extends BaseWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 6,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Produk Hampir Habis')
            ->description('Produk aktif dengan stok < 10, diurutkan dari terendah')
            ->headerActions([
                Action::make('view_all')
                    ->label('Lihat Semua Produk')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->size('sm')
                    ->url(fn (): string => ProductResource::getUrl('index')),
            ])
            ->query(
                Product::query()
                    ->where('is_active', true)
                    ->where('stok', '<', 10)
                    ->orderBy('stok', 'asc')
            )
            ->defaultSort('stok', 'asc')
            ->recordUrl(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]))
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
                        $state <= 5 => 'danger',
                        $state <= 10 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([5, 10, 25]);
    }
}
