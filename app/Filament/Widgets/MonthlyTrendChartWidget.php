<?php

namespace App\Filament\Widgets;

use App\Models\AccountingPeriod;
use App\Models\MonthlySummary;
use App\Services\AccountingService;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Support\RawJs;

class MonthlyTrendChartWidget extends ChartWidget
{
    public static function canView(): bool
    {
        return false;
    }

    protected ?string $heading = 'Tren Keuangan Bulanan';
    protected string $view = 'filament.widgets.sales-chart';
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 12,
        'lg' => 8,
        'xl' => 8,
    ];
    protected ?string $maxHeight = '500px';
    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $periods = AccountingPeriod::query()
            ->orderByDesc('start_date')
            ->limit(12)
            ->get();
        $summaries = MonthlySummary::query()
            ->whereIn('accounting_period_id', $periods->pluck('id'))
            ->get()
            ->keyBy('accounting_period_id');
        $accounting = app(AccountingService::class);
        $trend = $periods
            ->reverse()
            ->map(function (AccountingPeriod $period) use ($summaries, $accounting): array {
                $summary = $summaries->get($period->id);

                if ($summary) {
                    return [
                        'label' => $period->label,
                        'net_sales' => (float) $summary->net_sales,
                        'net_income' => (float) $summary->net_income,
                    ];
                }

                $income = $accounting->getIncomeStatement(
                    Carbon::parse($period->start_date)->startOfDay(),
                    Carbon::parse($period->end_date)->endOfDay(),
                );

                return [
                    'label' => $period->label . ' (berjalan)',
                    'net_sales' => (float) $income['penjualan_bersih'],
                    'net_income' => (float) $income['laba_rugi'],
                ];
            })
            ->values();

        return [
            'datasets' => [
                [
                    'label' => 'Penjualan Bersih',
                    'data' => $trend->pluck('net_sales')->all(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.08)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointBackgroundColor' => '#10b981',
                    'pointRadius' => 4,
                ],
                [
                    'label' => 'Laba Bersih',
                    'data' => $trend->pluck('net_income')->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.08)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointBackgroundColor' => '#f59e0b',
                    'pointRadius' => 4,
                ],
            ],
            'labels' => $trend->pluck('label')->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = context.parsed.y;
                                return context.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(value);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            maxTicksLimit: 12
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
