<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Filament\Support\RawJs;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Filament\Traits\ParsesGlobalFilters;

class SalesChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use ParsesGlobalFilters;

    protected static ?int $sort = 2;
    protected ?string $heading = 'Sales Chart';
    protected string $view = 'filament.widgets.sales-chart';
    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 12,
        'xl' => 8,
    ];

    protected ?string $maxHeight = '500px';
    
    protected ?string $pollingInterval = null;

    /**
     * Ringkasan Revenue + Order ditampilkan di atas chart via description.
     */
    public function getDescription(): string|Htmlable|null
    {
        $query = Invoice::where('status', 'paid');
        $this->applyDateFilter($query);

        $totalRevenue = (clone $query)->sum('grand_total');
        $totalOrders  = (clone $query)->count();
        $formatted    = 'Rp ' . number_format($totalRevenue, 0, ',', '.');

        $html = "
            <div class='flex flex-wrap items-center gap-6 mt-1 mb-2'>
                <div>
                    <span class='text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500'>Revenue</span>
                    <div class='text-xl font-bold text-gray-900 dark:text-white mt-0.5'>{$formatted}</div>
                </div>
                <div class='h-8 w-px bg-gray-200 dark:bg-gray-700'></div>
                <div>
                    <span class='text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500'>Total Order</span>
                    <div class='text-xl font-bold text-gray-900 dark:text-white mt-0.5'>{$totalOrders}</div>
                </div>
            </div>
        ";

        return new HtmlString($html);
    }

    protected function getData(): array
    {
        $labels = [];
        $data   = [];

        $baseQuery = Invoice::where('status', 'paid');
        [$startDate, $endDate, $preset] = $this->parseFilterDates();

        \Illuminate\Support\Facades\Log::info("SalesChart getData called", [
            'preset' => $preset,
            'startDate' => $startDate ? $startDate->toDateString() : null,
            'endDate' => $endDate ? $endDate->toDateString() : null,
        ]);

        // Ensure we always have a start and end date for chart plotting unless it's 'all'
        if ($preset === 'all' || (!$startDate && !$endDate)) {
            $results = (clone $baseQuery)
                ->select(
                    DB::raw('YEAR(issued_date) as year'),
                    DB::raw('SUM(grand_total) as total')
                )
                ->groupBy('year')
                ->orderBy('year')
                ->pluck('total', 'year')
                ->toArray();

            foreach ($results as $year => $total) {
                $labels[] = (string) $year;
                $data[]   = (float) $total;
            }
        } elseif ($preset === 'ytd' || $preset === 'last_365') {
            $results = (clone $baseQuery)
                ->whereBetween('issued_date', [$startDate, $endDate])
                ->select(DB::raw('MONTH(issued_date) as month'), DB::raw('YEAR(issued_date) as year'), DB::raw('SUM(grand_total) as total'))
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get();

            $monthsMap = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
            
            $current = $startDate->copy()->startOfMonth();
            $endMonth = $endDate->copy()->startOfMonth();
            
            while ($current->lte($endMonth)) {
                $y = $current->year;
                $m = $current->month;
                $found = $results->first(fn($r) => $r->year == $y && $r->month == $m);
                $labels[] = $monthsMap[$m] . ($preset === 'last_365' ? " '" . substr($y, 2) : '');
                $data[]   = $found ? (float) $found->total : 0;
                $current->addMonth();
            }

        } elseif ($preset === 'yesterday') {
            $results = (clone $baseQuery)
                ->whereDate('issued_date', $startDate)
                ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('SUM(grand_total) as total'))
                ->groupBy('hour')
                ->pluck('total', 'hour')
                ->toArray();

            for ($i = 8; $i <= 22; $i++) {
                $labels[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
                $data[]   = (float) ($results[$i] ?? 0);
            }
        } else {
            // Treat as daily ranges (last_7, this_month, prev_month, last_30, last_90, last_180, custom)
            $results = (clone $baseQuery)
                ->whereBetween('issued_date', [$startDate, $endDate])
                ->select(DB::raw('DATE(issued_date) as date'), DB::raw('SUM(grand_total) as total'))
                ->groupBy('date')
                ->pluck('total', 'date')
                ->toArray();

            $current = $startDate->copy();
            
            // If the range is huge (e.g. last_180), grouping by week might be better, but let's stick to daily for now
            // or just plot every day. Chart.js handles large datasets well.
            while ($current->lte($endDate)) {
                $dateString = $current->format('Y-m-d');
                $labels[]   = $current->format('d M');
                $data[]     = (float) ($results[$dateString] ?? 0);
                $current->addDay();
            }
        }

        return [
            'datasets' => [
                [
                    'label'                 => 'Pendapatan (Rp)',
                    'data'                  => $data,
                    'borderColor'           => '#db2777',
                    'backgroundColor'       => 'rgba(219, 39, 119, 0.08)',
                    'fill'                  => true,
                    'tension'               => 0.4,
                    'pointBackgroundColor'  => '#db2777',
                    'pointRadius'           => 4,
                ],
            ],
            'labels' => $labels,
        ];
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

    /**
     * Terapkan filter tanggal ke query builder.
     */
    private function applyDateFilter(\Illuminate\Database\Eloquent\Builder $query): void
    {
        [$startDate, $endDate, $preset] = $this->parseFilterDates();

        if ($startDate && $endDate) {
            $query->whereBetween('issued_date', [$startDate, $endDate]);
        }
    }
}
