<?php

namespace App\Filament\Resources\StockTransfers\Widgets;

use App\Models\StockTransfer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockTransferOverview extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $transfers = StockTransfer::with(['sourceProduct', 'targetProduct'])->get();

        $totalTransfersCount = $transfers->count();
        $totalQty = (int) $transfers->sum('quantity');

        // Nilai hasil transfer (nilai persediaan barang tujuan yang diperoleh)
        $totalTargetValue = (float) $transfers->sum(function (StockTransfer $t) {
            return (float) $t->quantity * (float) ($t->targetProduct?->harga_beli ?? 0);
        });

        // Nilai barang asal (HPP produk sumber yang dikurangi)
        $totalSourceValue = (float) $transfers->sum(function (StockTransfer $t) {
            return (float) $t->quantity * (float) ($t->sourceProduct?->harga_beli ?? 0);
        });

        // Net varians nilai (Target Value - Source Value)
        $netVarianceValue = $totalTargetValue - $totalSourceValue;
        $variancePrefix = $netVarianceValue > 0 ? '+' : '';
        $varianceColor = match (true) {
            $netVarianceValue > 0 => 'success',
            $netVarianceValue < 0 => 'warning',
            default => 'gray',
        };
        $varianceDescription = match (true) {
            $netVarianceValue > 0 => 'Kenaikan nilai persediaan (Surplus)',
            $netVarianceValue < 0 => 'Penurunan nilai persediaan (Defisit)',
            default => 'Nilai asal dan tujuan seimbang',
        };

        return [
            Stat::make('Nilai Hasil Transfer', 'Rp '.number_format($totalTargetValue, 0, ',', '.'))
                ->description('Total nilai HPP barang yang diterima')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Nilai Barang Asal', 'Rp '.number_format($totalSourceValue, 0, ',', '.'))
                ->description('Total nilai HPP barang yang dikurangi')
                ->icon('heroicon-o-arrow-up-right')
                ->color('info'),

            Stat::make('Total Qty Ditransfer', number_format($totalQty, 0, ',', '.').' pcs')
                ->description("Dari {$totalTransfersCount} dokumen transfer stok")
                ->icon('heroicon-o-arrows-right-left')
                ->color('primary'),

            Stat::make('Selisih Nilai (Varians)', $variancePrefix.'Rp '.number_format($netVarianceValue, 0, ',', '.'))
                ->description($varianceDescription)
                ->icon('heroicon-o-scale')
                ->color($varianceColor),
        ];
    }
}
