<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\ParsesGlobalFilters;
use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Kondisi operasional saat ini. TIDAK mengikuti filter periode global.
 */
class CurrentStatusOverview extends BaseWidget
{
    use ParsesGlobalFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 12,
    ];

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Kondisi Operasional Saat Ini';

    protected function getDescription(): ?string
    {
        return 'Tidak mengikuti filter periode · Kondisi realtime · Diperbarui '.now()->format('H.i');
    }

    protected function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
        ];
    }

    protected function getStats(): array
    {
        try {
            $state = app(DashboardMetricsService::class)->currentState();
        } catch (Throwable $exception) {
            report($exception);

            return [
                Stat::make('Kondisi Realtime', 'Gagal memuat data')
                    ->description('Terjadi kesalahan saat mengambil data operasional.')
                    ->descriptionIcon('heroicon-m-exclamation-circle')
                    ->descriptionColor('danger')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->columnSpan('full'),
            ];
        }

        $updatedAt = now()->format('H.i');
        $snapshot = '<span class="block text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">Kondisi realtime · Diperbarui '.$updatedAt.'</span>';

        $pendingDetail = $state['pending_count'] > 0
            ? 'Menunggu pembayaran · '.$this->formatRupiah($state['pending_value'])
            : 'Tidak ada transaksi menunggu pembayaran';

        $stockDetail = $state['low_stock_count'] > 0
            ? 'Produk aktif dengan stok kurang dari '.DashboardMetricsService::LOW_STOCK_THRESHOLD
            : 'Semua stok produk aktif aman';

        if ($state['negative_stock_count'] > 0) {
            $stockDetail .= " · {$state['negative_stock_count']} stok negatif";
        }

        return [
            Stat::make('Transaksi Pending', $this->statValue(number_format($state['pending_count'], 0, ',', '.')))
                ->description(new HtmlString('<span class="block text-sm font-medium text-gray-700 dark:text-gray-200">'.e($pendingDetail).'</span>'.$snapshot))
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Stok Menipis', $this->statValue(number_format($state['low_stock_count'], 0, ',', '.')))
                ->description(new HtmlString('<span class="block text-sm font-medium text-gray-700 dark:text-gray-200">'.e($stockDetail).'</span>'.$snapshot))
                ->descriptionColor($state['negative_stock_count'] > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
