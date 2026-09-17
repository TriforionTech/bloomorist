<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use App\Filament\Pages\ProductSalesReport;
use Filament\Tables\Filters\Filter;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Filament\Traits\ParsesGlobalFilters;

class TopProducts extends ChartWidget
{
    use InteractsWithPageFilters;
    use ParsesGlobalFilters;

    protected static ?int $sort = 5;
    protected ?string $heading = 'Top 10 Selling Products';

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 12,
        'xl' => 12,
    ];

    protected ?string $maxHeight = '340px';

    public function getDescription(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        $html = "Berdasarkan total qty terjual dari invoice lunas";
        return new \Illuminate\Support\HtmlString($html);
    }

    protected function getData(): array
    {
        $productTable     = (new Product)->getTable();
        $invoiceItemTable = 'bl_invoice_items_t';
        $invoiceTable     = 'bl_invoices_t';

        [$startDate, $endDate] = $this->parseFilterDates();

        $query = Product::query()
            ->select(
                "{$productTable}.id",
                "{$productTable}.nama"
            )
            ->whereNotIn("{$productTable}.nama", ['Box', 'Wrapping']);

        if ($startDate && $endDate) {
            $query->selectRaw("COALESCE(SUM(CASE WHEN {$invoiceTable}.status = 'paid' AND {$invoiceTable}.issued_date BETWEEN ? AND ? THEN {$invoiceItemTable}.quantity ELSE 0 END), 0) as total_sold", [$startDate, $endDate]);
        } else {
            $query->selectRaw("COALESCE(SUM(CASE WHEN {$invoiceTable}.status = 'paid' THEN {$invoiceItemTable}.quantity ELSE 0 END), 0) as total_sold");
        }

        $query->leftJoin($invoiceItemTable, "{$productTable}.id", '=', "{$invoiceItemTable}.product_id")
            ->leftJoin($invoiceTable, "{$invoiceItemTable}.invoice_id", '=', "{$invoiceTable}.id")
            ->groupBy(
                "{$productTable}.id",
                "{$productTable}.nama"
            )
            ->orderByDesc('total_sold')
            ->orderBy("{$productTable}.nama")
            ->limit(10);

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