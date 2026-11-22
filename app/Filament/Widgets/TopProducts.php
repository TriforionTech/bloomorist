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

class TopProducts extends ChartWidget
{
    use HandlesChartState;
    use InteractsWithPageFilters;
    use ParsesGlobalFilters;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Top 10 Produk Terlaris';

    protected string $view = 'filament.widgets.dashboard-chart';

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 6,
    ];

    protected ?string $maxHeight = '340px';

    /**
     * @var array<int, string>
     */
    private const PALETTE = [
        '#db2777', '#f59e0b', '#10b981', '#0ea5e9', '#8b5cf6',
        '#f97316', '#14b8a6', '#ef4444', '#6366f1', '#84cc16',
    ];

    public function getDescription(): string|Htmlable|null
    {
        return new HtmlString('Item terjual dari invoice lunas · Box &amp; Wrapping tidak dihitung');
    }

    public function getEmptyStateMessage(): string
    {
        return 'Belum ada produk terjual pada periode ini';
    }

    protected function getData(): array
    {
        return $this->guardChartData(function (): array {
            $products = app(DashboardMetricsService::class)->topProducts($this->dashboardPeriod(), 10);

            return [
                'hasData' => $products->isNotEmpty(),
                'chart' => [
                    'datasets' => [
                        [
                            'label' => 'Qty Terjual',
                            'data' => $products->pluck('qty')->all(),
                            'backgroundColor' => array_slice(self::PALETTE, 0, $products->count()),
                            'borderWidth' => 2,
                        ],
                    ],
                    'labels' => $products->pluck('name')->all(),
                ],
            ];
        });
    }

    protected function getOptions(): array|RawJs
    {
        return RawJs::make(<<<'JS'
            {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { display: false },
                    y: { display: false },
                },
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12 } },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const total = context.dataset.data.reduce((sum, value) => sum + value, 0);
                                const percent = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + new Intl.NumberFormat('id-ID').format(context.parsed) + ' pcs (' + percent + '%)';
                            }
                        }
                    }
                }
            }
        JS);
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
