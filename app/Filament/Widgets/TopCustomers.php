<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HandlesChartState;
use App\Filament\Traits\ParsesGlobalFilters;
use App\Services\DashboardMetricsService;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TopCustomers extends ChartWidget
{
    use HandlesChartState;
    use InteractsWithPageFilters;
    use ParsesGlobalFilters;

    protected static ?int $sort = 5;

    protected ?string $heading = 'Pelanggan Teratas';

    protected ?string $description = 'Top 10 pelanggan berdasarkan total belanja (invoice lunas)';

    protected string $view = 'filament.widgets.dashboard-chart';

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 6,
    ];

    protected ?string $maxHeight = '340px';

    public function getEmptyStateMessage(): string
    {
        return 'Belum ada belanja pelanggan pada periode ini';
    }

    public function getEmptyStateIcon(): string
    {
        return 'heroicon-o-users';
    }

    protected function getData(): array
    {
        return $this->guardChartData(function (): array {
            $customers = app(DashboardMetricsService::class)->topCustomers($this->dashboardPeriod(), 10);

            return [
                'hasData' => $customers->isNotEmpty(),
                'chart' => [
                    'datasets' => [
                        [
                            'label' => 'Total Belanja (Rp)',
                            'data' => $customers->pluck('total')->all(),
                            'backgroundColor' => 'rgba(219, 39, 119, 0.75)',
                            'borderColor' => 'rgba(190, 24, 93, 0.9)',
                            'borderWidth' => 1,
                            'borderRadius' => 4,
                        ],
                    ],
                    'labels' => $customers->pluck('name')->all(),
                ],
            ];
        });
    }

    protected function getOptions(): array|RawJs
    {
        return RawJs::make(<<<'JS'
            {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => 'Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.x)
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { display: true },
                        ticks: {
                            callback: (value) => {
                                if (value >= 1000000) return 'Rp ' + (value / 1000000).toFixed(1) + 'jt';
                                if (value >= 1000) return 'Rp ' + (value / 1000).toFixed(0) + 'rb';
                                return 'Rp ' + value;
                            }
                        }
                    },
                    y: { grid: { display: false } },
                },
            }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
