<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class SalesChart extends ChartWidget
{
    protected ?string $heading = 'Grafik Penjualan';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '500px';

    public ?string $filter = 'week';

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Hari Ini',
            'week'  => '7 Hari Terakhir',
            'month' => 'Bulan Ini',
            'year'  => 'Tahun Ini',
        ];
    }

    /**
     * Ringkasan Revenue + Order ditampilkan di atas chart via description.
     */
    public function getDescription(): string | Htmlable | null
    {
        $baseQuery = Invoice::where('status', 'paid');
        $this->applyDateFilter($baseQuery);

        $totalRevenue = (clone $baseQuery)->sum('grand_total');
        $totalOrders  = (clone $baseQuery)->count();

        $formatted = 'Rp ' . number_format($totalRevenue, 0, ',', '.');

        return new HtmlString("
            <div class='flex flex-wrap items-center gap-6 mt-1 mb-2'>
                <div>
                    <span class='text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500'>Revenue</span>
                    <div class='text-xl font-bold text-gray-900 dark:text-white mt-0.5'>{$formatted}</div>
                </div>
                <div class='h-8 w-px bg-gray-200 dark:bg-gray-700'></div>
                <br>
                <div>
                    <span class='text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500'>Total Order</span>
                    <div class='text-xl font-bold text-gray-900 dark:text-white mt-0.5'>{$totalOrders}</div>
                </div>
            </div>
        ");
    }

    protected function getData(): array
    {
        $labels = [];
        $data   = [];

        $baseQuery = Invoice::where('status', 'paid');

        if ($this->filter === 'today') {
            $results = (clone $baseQuery)
                ->whereDate('issued_date', Carbon::today())
                ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('SUM(grand_total) as total'))
                ->groupBy('hour')
                ->pluck('total', 'hour')
                ->toArray();

            for ($i = 8; $i <= 22; $i++) {
                $labels[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
                $data[]   = (float) ($results[$i] ?? 0);
            }

        } elseif ($this->filter === 'month') {
            $results = (clone $baseQuery)
                ->whereBetween('issued_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                ->select(DB::raw('DATE(issued_date) as date'), DB::raw('SUM(grand_total) as total'))
                ->groupBy('date')
                ->pluck('total', 'date')
                ->toArray();

            for ($i = 1; $i <= Carbon::now()->daysInMonth; $i++) {
                $dateString = Carbon::now()->setDay($i)->format('Y-m-d');
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

            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
            foreach ($months as $index => $month) {
                $labels[] = $month;
                $data[]   = (float) ($results[$index + 1] ?? 0);
            }

        } else {
            // Default: 7 hari terakhir
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
                    'label'           => 'Pendapatan (Rp)',
                    'data'            => $data,
                    'borderColor'     => '#db2777',
                    'backgroundColor' => 'rgba(219, 39, 119, 0.08)',
                    'fill'            => true,
                    'tension'         => 0.4,
                    'pointBackgroundColor' => '#db2777',
                    'pointRadius'     => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'responsive'          => true,
            'maintainAspectRatio' => false,
            'plugins'    => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks'       => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
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
            'today' => $query->whereDate('issued_date', Carbon::today()),
            'month' => $query->whereBetween('issued_date', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ]),
            'year'  => $query->whereYear('issued_date', Carbon::now()->year),
            default => $query->whereBetween('issued_date', [
                Carbon::today()->subDays(6),
                Carbon::today(),
            ]),
        };
    }
}
