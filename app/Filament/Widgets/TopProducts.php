<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopProducts extends ChartWidget
{
    protected static ?int $sort = 3;
    protected ?string $heading = 'Top Selling Products';
    protected ?string $description = 'Berdasarkan total qty terjual dari invoice lunas';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '380px';

    protected ?string $pollingInterval = null;

    public ?string $filter = 'week';

    protected function getFilters(): ?array
    {
        return [
            'today'  => 'Hari Ini',
            'week'   => '7 Hari Terakhir',
            'month'  => 'Bulan Ini',
            'year'   => 'Tahun Ini',
            'all'    => 'Semua Waktu',
        ];
    }

    private function getDateRange(): array
    {
        return match ($this->filter) {
            'week'  => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'month' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'year'  => [now()->startOfYear(), now()->endOfDay()],
            'all'   => [null, null],
            default => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
        };
    }

    protected function getData(): array
    {
        $productTable     = (new Product)->getTable();
        $invoiceItemTable = 'bl_invoice_items_t';
        $invoiceTable     = 'bl_invoices_t';

        [$startDate, $endDate] = $this->getDateRange();

        $query = Product::query()
            ->select(
                "{$productTable}.id",
                "{$productTable}.nama",
                DB::raw("SUM({$invoiceItemTable}.quantity) as total_sold")
            )
            ->join($invoiceItemTable, "{$productTable}.id", '=', "{$invoiceItemTable}.product_id")
            ->join($invoiceTable, "{$invoiceItemTable}.invoice_id", '=', "{$invoiceTable}.id")
            ->where("{$invoiceTable}.status", 'paid')
            ->whereNotIn("{$productTable}.nama", ['Box', 'Wrapping'])
            ->groupBy(
                "{$productTable}.id",
                "{$productTable}.nama"
            )
            ->orderByDesc('total_sold')
            ->limit(10);
        
        if ($startDate && $endDate) {
            $query->whereBetween("{$invoiceTable}.created_at", [$startDate, $endDate]);
        }

        $results = $query->get();

        $labels     = $results->pluck('nama')->toArray();
        $quantities = $results->pluck('total_sold')->map(fn($v) => (int) $v)->toArray();
        $colors     = array_fill(0, count($results), 'rgba(219, 39, 119, 0.75)');
        $borders    = array_fill(0, count($results), 'rgba(190, 24, 93, 0.9)');

        return [
            'datasets' => [
                [
                    'label'           => 'Qty Terjual',
                    'data'            => $quantities,
                    'backgroundColor' => $colors,
                    'borderColor'     => $borders,
                    'borderWidth'     => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }
 
    protected function getOptions(): array|RawJs
    {
        return RawJs::make(<<<'JS'
            {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { display: true },
                    },
                    y: {
                        grid: { display: false },
                    },
                },
            }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}