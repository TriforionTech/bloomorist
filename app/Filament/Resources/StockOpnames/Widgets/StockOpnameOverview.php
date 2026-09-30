<?php

namespace App\Filament\Resources\StockOpnames\Widgets;

use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockOpnameOverview extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $totalPhysicalQty = (int) StockOpnameItem::sum('physical_qty');
        $totalProductsCount = StockOpnameItem::distinct('product_id')->count('product_id');
        $totalRows = StockOpnameItem::count();
        $totalOpnames = StockOpname::count();
        $postedCount = StockOpname::where('status', 'posted')->count();
        $draftCount = StockOpname::where('status', 'draft')->count();
        $totalVariance = (int) StockOpnameItem::sum('variance');

        $variancePrefix = $totalVariance > 0 ? '+' : '';
        $varianceDescription = match (true) {
            $totalVariance === 0 => 'Fisik cocok dengan sistem',
            $totalVariance > 0 => 'Surplus fisik vs sistem',
            default => 'Penyusutan fisik vs sistem',
        };

        $varianceColor = match (true) {
            $totalVariance === 0 => 'success',
            $totalVariance > 0 => 'warning',
            default => 'danger',
        };

        return [
            Stat::make('Total Fisik Terinput', number_format($totalPhysicalQty, 0, ',', '.').' pcs')
                ->description('Total kuantitas fisik barang di-opname')
                ->icon('heroicon-o-cube')
                ->color('success'),

            Stat::make('Item Produk Terdata', number_format($totalProductsCount, 0, ',', '.').' Produk')
                ->description("{$totalRows} baris pencatatan opname")
                ->icon('heroicon-o-tag')
                ->color('primary'),

            Stat::make('Dokumen Opname', number_format($totalOpnames, 0, ',', '.').' Dokumen')
                ->description("{$postedCount} Posted · {$draftCount} Draft")
                ->icon('heroicon-o-document-text')
                ->color('info'),

            Stat::make('Net Selisih (Varians)', $variancePrefix.number_format($totalVariance, 0, ',', '.').' pcs')
                ->description($varianceDescription)
                ->icon('heroicon-o-arrows-up-down')
                ->color($varianceColor),
        ];
    }
}
