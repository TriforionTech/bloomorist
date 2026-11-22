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
 * KPI historis: seluruh angka mengikuti filter periode global dashboard.
 */
class StatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;
    use ParsesGlobalFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 12,
    ];

    protected ?string $heading = 'Ringkasan Periode';

    protected function getDescription(): ?string
    {
        $period = $this->dashboardPeriod();

        return "Mengikuti filter periode · {$period->presetLabel()} ({$period->rangeLabel()})";
    }

    protected function getColumns(): int|array
    {
        return [
            'default' => 1,
            'sm' => 2,
            'lg' => 3,
            'xl' => 5,
        ];
    }

    protected function getStats(): array
    {
        try {
            return $this->buildStats();
        } catch (Throwable $exception) {
            report($exception);

            return [
                Stat::make('Ringkasan Periode', 'Gagal memuat data')
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
        $expense = $metrics->expenseTotal($period);
        $profit = $metrics->profit($period);

        $previousSummary = $previousPeriod ? $metrics->invoiceSummary($previousPeriod) : null;
        $previousExpense = $previousPeriod ? $metrics->expenseTotal($previousPeriod) : null;
        $previousProfit = $previousPeriod ? $metrics->profit($previousPeriod) : null;

        return [
            $this->makeStat(
                $period,
                'Total Transaksi',
                number_format($summary['total_count'], 0, ',', '.'),
                "{$summary['paid_count']} lunas · semua status",
                'heroicon-o-document-text',
                'info',
                $summary['total_count'],
                $previousSummary['total_count'] ?? null,
            ),

            $this->makeStat(
                $period,
                'Pendapatan',
                $this->formatRupiah($summary['revenue']),
                "Dari {$summary['paid_count']} invoice lunas",
                'heroicon-o-banknotes',
                'primary',
                $summary['revenue'],
                $previousSummary['revenue'] ?? null,
            ),

            $this->makeStat(
                $period,
                'Pengeluaran',
                $this->formatRupiah($expense),
                'Pengeluaran berstatus posted',
                'heroicon-o-receipt-percent',
                'danger',
                $expense,
                $previousExpense,
                higherIsBetter: false,
            ),

            $this->makeProfitStat($period, 'Laba Kotor', 'gross_profit', $profit, $previousProfit),
            $this->makeProfitStat($period, 'Laba Bersih', 'net_profit', $profit, $previousProfit),
        ];
    }

    private function makeStat(
        DashboardPeriod $period,
        string $label,
        string $value,
        string $caption,
        string $icon,
        string $color,
        float|int $current,
        float|int|null $previous,
        bool $higherIsBetter = true,
    ): Stat {
        $stat = Stat::make($label, $this->statValue($value, $caption))
            ->icon($icon)
            ->color($color);

        $comparison = $this->comparisonDescriptor($period, $current, $previous, $higherIsBetter);

        if ($comparison !== null) {
            $stat->description($comparison['text'])
                ->descriptionColor($comparison['color'])
                ->descriptionIcon($comparison['icon']);
        }

        return $stat;
    }

    /**
     * @param  array{gross_profit: float, net_profit: float}|null  $profit
     * @param  array{gross_profit: float, net_profit: float}|null  $previousProfit
     */
    private function makeProfitStat(
        DashboardPeriod $period,
        string $label,
        string $key,
        ?array $profit,
        ?array $previousProfit,
    ): Stat {
        if ($profit === null) {
            return Stat::make($label, $this->statValue('—', 'Belum tersedia'))
                ->icon('heroicon-o-calculator')
                ->color('gray')
                ->description('Butuh Periode Akuntansi yang sama dengan rentang ini dan persediaan akhir terisi')
                ->descriptionColor('gray');
        }

        return $this->makeStat(
            $period,
            $label,
            $this->formatRupiah($profit[$key]),
            'Sesuai Laporan Laba Rugi',
            'heroicon-o-calculator',
            $profit[$key] >= 0 ? 'success' : 'danger',
            $profit[$key],
            $previousProfit[$key] ?? null,
        );
    }
}
