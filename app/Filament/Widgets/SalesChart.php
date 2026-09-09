<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Filament\Support\RawJs;

class SalesChart extends ChartWidget
{
    protected static ?int $sort = 2;
    protected ?string $heading = 'Sales Chart';
    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 12,
        'xl' => 8,
    ];

    protected ?string $maxHeight = '500px';
    
    protected ?string $pollingInterval = null;

    public ?string $filter = 'month';
    public ?string $startDate = null;
    public ?string $endDate = null;

    protected function getFilters(): ?array
    {
        return [
            'today'  => 'Today',
            'week'   => 'Last 7 Days',
            'month'  => 'This Month',
            'year'   => 'This Year',
            'all'    => 'Year by Year',
            'custom' => 'Custom',
        ];
    }

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

        if ($this->filter === 'custom') {
            $html .= "
                <div class='mt-3 flex flex-wrap items-center gap-3 bg-gray-50 dark:bg-gray-800 p-3 rounded-lg border border-gray-200 dark:border-gray-700 max-w-md'>
                    <label class='text-sm font-medium text-gray-700 dark:text-gray-300'>Dari:</label>
                    <input type='date' wire:model.live='startDate' class='text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500'>
                    
                    <label class='text-sm font-medium text-gray-700 dark:text-gray-300 ml-2'>Sampai:</label>
                    <input type='date' wire:model.live='endDate' class='text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500'>
                </div>
            ";
        }

        return new HtmlString($html);
    }

    protected function getData(): array
    {
        $labels = [];
        $data   = [];

        $baseQuery = Invoice::where('status', 'paid');

        if ($this->filter === 'today') {
            $results = (clone $baseQuery)
                ->whereDate('created_at', Carbon::today())
                ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('SUM(grand_total) as total'))
                ->groupBy('hour')
                ->pluck('total', 'hour')
                ->toArray();

            for ($i = 8; $i <= 22; $i++) {
                $labels[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
                $data[]   = (float) ($results[$i] ?? 0);
            }

        } elseif ($this->filter === 'month') {
            $now = Carbon::now();
            $results = (clone $baseQuery)
                ->whereBetween('issued_date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
                ->select(DB::raw('DATE(issued_date) as date'), DB::raw('SUM(grand_total) as total'))
                ->groupBy('date')
                ->pluck('total', 'date')
                ->toArray();

            $daysInMonth = $now->daysInMonth;
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $dateString = $now->copy()->setDay($i)->format('Y-m-d');
                $labels[]   = $i;
                $data[]     = (float) ($results[$dateString] ?? 0);
            }

        } elseif ($this->filter === 'year') {
            $results = (clone $baseQuery)
                ->whereYear('issued_date', Carbon::now()->year)
                ->select(DB::raw('MONTH(issued_date) as month'), DB::raw('SUM(grand_total) as total'))
                ->groupBy('month')
                ->pluck('total', 'month')
                ->toArray();

            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            foreach ($months as $index => $month) {
                $labels[] = $month;
                $data[]   = (float) ($results[$index + 1] ?? 0);
            }

        } elseif ($this->filter === 'all') {
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

        } elseif ($this->filter === 'custom') {
            if ($this->startDate && $this->endDate) {
                $start = Carbon::parse($this->startDate)->startOfDay();
                $end   = Carbon::parse($this->endDate)->endOfDay();
                
                $results = (clone $baseQuery)
                    ->whereBetween('issued_date', [$start, $end])
                    ->select(DB::raw('DATE(issued_date) as date'), DB::raw('SUM(grand_total) as total'))
                    ->groupBy('date')
                    ->pluck('total', 'date')
                    ->toArray();

                // Generate labels from start to end
                $current = $start->copy();
                while ($current->lte($end)) {
                    $dateString = $current->format('Y-m-d');
                    $labels[]   = $current->format('d M');
                    $data[]     = (float) ($results[$dateString] ?? 0);
                    $current->addDay();
                }
            }
        } else {
            // 7 hari terakhir
            $results = (clone $baseQuery)
                ->whereBetween('issued_date', [Carbon::today()->subDays(6), Carbon::today()])
                ->select(DB::raw('DATE(issued_date) as date'), DB::raw('SUM(grand_total) as total'))
                ->groupBy('date')
                ->pluck('total', 'date')
                ->toArray();

            for ($i = 6; $i >= 0; $i--) {
                $dateString = Carbon::today()->subDays($i)->format('Y-m-d');
                $labels[]   = Carbon::today()->subDays($i)->format('d M');
                $data[]     = (float) ($results[$dateString] ?? 0);
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
        match ($this->filter) {
            'today' => $query->whereDate('created_at', Carbon::today()),
            'month' => $query->whereBetween('issued_date', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ]),
            'year'  => $query->whereYear('issued_date', Carbon::now()->year),
            'all', 'custom' => null,
            default => $query->whereBetween('issued_date', [
                Carbon::today()->subDays(6),
                Carbon::today(),
            ]),
        };
        
        if ($this->filter === 'custom') {
            if ($this->startDate && $this->endDate) {
                $query->whereBetween('issued_date', [
                    Carbon::parse($this->startDate)->startOfDay(),
                    Carbon::parse($this->endDate)->endOfDay(),
                ]);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
    }
}
