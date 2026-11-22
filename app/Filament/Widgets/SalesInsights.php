<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\ParsesGlobalFilters;
use App\Services\DashboardMetricsService;
use App\Services\DashboardPeriod;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

/**
 * Insight penjualan historis: seluruh metrik mengikuti filter periode global.
 */
class SalesInsights extends BaseWidget
{
    use InteractsWithPageFilters;
    use ParsesGlobalFilters;

    public const PAID_RATE_GOOD = 80.0;

    public const PAID_RATE_WARNING = 50.0;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 12,
    ];

    protected ?string $heading = 'Insight Penjualan';

    protected function getDescription(): ?string
    {
        $period = $this->dashboardPeriod();

        return "Mengikuti filter periode · {$period->presetLabel()} ({$period->rangeLabel()})";
    }

    protected function getColumns(): int|array|null
    {
        return [
            'default' => 1,
            'sm' => 2,
            'xl' => 4,
        ];
    }

    /**
     * Warna indikator % Invoice Paid: hijau >= 80%, oranye 50% - <80%, merah < 50%.
     */
    public static function paidRateColor(float $percent): string
    {
        return match (true) {
            $percent >= self::PAID_RATE_GOOD => 'success',
            $percent >= self::PAID_RATE_WARNING => 'warning',
            default => 'danger',
        };
    }

    protected function getStats(): array
    {
        try {
            return $this->buildStats();
        } catch (Throwable $exception) {
            report($exception);

            return [
                Stat::make('Insight Penjualan', 'Gagal memuat data')
                    ->description('Terjadi kesalahan saat mengambil data. Muat ulang halaman atau ubah periode.')
                    ->descriptionIcon('heroicon-m-exclamation-circle')
                    ->descriptionColor('danger')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->columnSpan('full'),
            ];
        }
    }

    /**
     * @return array<int, Stat>
     */
    private function buildStats(): array
    {
        $period = $this->dashboardPeriod();
        $metrics = app(DashboardMetricsService::class);
        $previousPeriod = $period->comparison();

        $summary = $metrics->invoiceSummary($period);
        $quantity = $metrics->soldQuantity($period);
        $topProduct = $metrics->topProducts($period, 1)->first();

        $aov = $summary['paid_count'] > 0 ? (int) round($summary['revenue'] / $summary['paid_count']) : 0;

        $previousAov = null;
        $previousQuantity = null;
        if ($previousPeriod) {
            $previousSummary = $metrics->invoiceSummary($previousPeriod);
            $previousAov = $previousSummary['paid_count'] > 0
                ? (int) round($previousSummary['revenue'] / $previousSummary['paid_count'])
                : null;
            $previousQuantity = $metrics->soldQuantity($previousPeriod);
        }

        $paidPercent = $summary['total_count'] > 0
            ? round(($summary['paid_count'] / $summary['total_count']) * 100, 1)
            : null;

        return [
            $this->withComparison(
                Stat::make('AOV', $this->statValue($this->formatRupiah($aov), 'Harga rata-rata invoice lunas'))
                    ->icon('heroicon-o-calculator')
                    ->color('primary'),
                $period,
                $aov,
                $previousAov,
            ),

            $this->withComparison(
                Stat::make('Produk Terjual', $this->statValue(number_format($quantity, 0, ',', '.').' pcs', 'Qty dari invoice lunas'))
                    ->icon('heroicon-o-shopping-bag')
                    ->color('success'),
                $period,
                $quantity,
                $previousQuantity,
            ),

            Stat::make('Produk Terlaris', $this->statValue($topProduct['name'] ?? 'Belum ada data', $topProduct ? "{$topProduct['qty']} pcs terjual" : 'Belum ada produk terjual'))
                ->icon('heroicon-o-star')
                ->color('warning'),

            $this->makePaidRateStat($paidPercent, $summary['paid_count'], $summary['total_count']),
        ];
    }

    private function withComparison(Stat $stat, DashboardPeriod $period, float|int $current, float|int|null $previous): Stat
    {
        $comparison = $this->comparisonDescriptor($period, $current, $previous);

        if ($comparison !== null) {
            $stat->description($comparison['text'])
                ->descriptionColor($comparison['color'])
                ->descriptionIcon($comparison['icon']);
        }

        return $stat;
    }

    private function makePaidRateStat(?float $percent, int $paidCount, int $totalCount): Stat
    {
        if ($percent === null) {
            return Stat::make('Invoice Lunas', $this->statValue('—', 'Belum ada invoice pada periode ini'))
                ->icon('heroicon-o-check-circle')
                ->color('gray');
        }

        $color = self::paidRateColor($percent);
        $display = rtrim(rtrim(number_format($percent, 1, ',', '.'), '0'), ',');

        return Stat::make('Invoice Lunas', $this->statValue("{$display}%", "{$paidCount} dari {$totalCount} invoice lunas"))
            ->icon('heroicon-o-check-circle')
            ->color($color)
            ->description('Hijau ≥ 80% · Oranye 50–79,9% · Merah < 50%')
            ->descriptionColor($color)
            ->descriptionIcon(match ($color) {
                'success' => 'heroicon-m-check-circle',
                'warning' => 'heroicon-m-exclamation-circle',
                default => 'heroicon-m-x-circle',
            });
    }
}
