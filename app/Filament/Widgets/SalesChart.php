<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HandlesChartState;
use App\Filament\Traits\ParsesGlobalFilters;
use App\Services\DashboardMetricsService;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Throwable;

class SalesChart extends ChartWidget
{
    use HandlesChartState;
    use InteractsWithPageFilters;
    use ParsesGlobalFilters;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Grafik Penjualan';

    protected string $view = 'filament.widgets.sales-chart';

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 12,
    ];

    protected ?string $maxHeight = '500px';

    protected ?string $pollingInterval = null;

    /**
     * Ringkasan pendapatan + jumlah invoice lunas pada periode aktif.
     */
    public function getDescription(): string|Htmlable|null
    {
        try {
            $period = $this->dashboardPeriod();
            $summary = app(DashboardMetricsService::class)->invoiceSummary($period);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $revenue = e($this->formatRupiah($summary['revenue']));
        $paidCount = number_format($summary['paid_count'], 0, ',', '.');
        $range = e($period->presetLabel().' · '.$period->rangeLabel());

        return new HtmlString("
            <div class='flex flex-wrap items-center gap-x-6 gap-y-2 mt-1 mb-2'>
                <div>
                    <span class='text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400'>Pendapatan</span>
                    <div class='text-lg font-bold text-gray-900 dark:text-white mt-0.5'>{$revenue}</div>
                </div>
                <div class='h-8 w-px bg-gray-200 dark:bg-gray-700'></div>
                <div>
                    <span class='text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400'>Invoice Lunas</span>
                    <div class='text-lg font-bold text-gray-900 dark:text-white mt-0.5'>{$paidCount}</div>
                </div>
                <div class='text-sm font-medium text-gray-600 dark:text-gray-300'>{$range}</div>
            </div>
        ");
    }

    public function getEmptyStateMessage(): string
    {
        return 'Belum ada transaksi pada periode ini';
    }

    protected function getData(): array
    {
        return $this->guardChartData(function (): array {
            $series = app(DashboardMetricsService::class)->salesSeries($this->dashboardPeriod());

            $intervalLabel = match ($series['interval']) {
                'hour' => 'per jam',
                'day' => 'per hari',
                'month' => 'per bulan',
                default => 'per tahun',
            };

            return [
                'hasData' => $series['total'] > 0,
                'chart' => [
                    'datasets' => [
                        [
                            'label' => "Pendapatan {$intervalLabel} (Rp)",
                            'data' => $series['data'],
                            'borderColor' => '#db2777',
                            'backgroundColor' => 'rgba(219, 39, 119, 0.08)',
                            'fill' => true,
                            'tension' => 0.4,
                            'pointBackgroundColor' => '#db2777',
                            'pointRadius' => count($series['data']) > 31 ? 2 : 4,
                        ],
                    ],
                    'labels' => $series['labels'],
                ],
            ];
        });
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = context.parsed.y;
                                return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            maxTicksLimit: 15
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            callback: (value) => {
                                if (value >= 1000000) return 'Rp ' + (value / 1000000).toFixed(1) + 'jt';
                                if (value >= 1000) return 'Rp ' + (value / 1000).toFixed(0) + 'rb';
                                return 'Rp ' + value;
                            }
                        }
                    }
                }
            }
        JS);
    }

    protected function getType(): string
    {
        return 'line';
    }
}
