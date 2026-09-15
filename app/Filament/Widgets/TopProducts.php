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

class TopProducts extends ChartWidget
{
    protected static ?int $sort = 4;
    protected ?string $heading = 'Top 10 Selling Products';

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 12,
        'xl' => 12,
    ];

    protected ?string $maxHeight = '340px';

    public ?string $filter = 'month';
    public ?string $startDate = null;
    public ?string $endDate = null;

    public function getDescription(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        $html = "Berdasarkan total qty terjual dari invoice lunas";

        if ($this->filter === 'custom') {
            $html .= "
                <div class='mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400'>
                    <input type='date' wire:model.live='startDate' style='border:none; background:transparent; padding:0; box-shadow:none;' class='text-sm font-semibold text-primary-600 dark:text-primary-400 focus:ring-0 cursor-pointer'>
                    <span class='text-gray-400 dark:text-gray-600'>-</span>
                    <input type='date' wire:model.live='endDate' style='border:none; background:transparent; padding:0; box-shadow:none;' class='text-sm font-semibold text-primary-600 dark:text-primary-400 focus:ring-0 cursor-pointer'>
                </div>
            ";
        }

        return new \Illuminate\Support\HtmlString($html);
    }

    // // ini kyknya gabisa > need review
    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Action::make('viewReport')
    //             ->label('View Full Report')
    //             ->icon('heroicon-o-arrow-top-right-on-square')
    //             ->color('gray')
    //             ->size('sm')
    //             ->url(fn (): string => ProductSalesReport::getUrl([
    //                 'filter' => $this->filter,
    //             ])),
    //     ];
    // }

    protected function getFilters(): ?array
    {
        return [
            'today'  => 'Today',
            'week'   => 'Last 7 Days',
            'month'  => 'This Month',
            'prev_month' => 'Previous Month',
            'year'   => 'This Year',
            'all'    => 'All Time',
            'custom' => 'Custom',
        ];
    }

    private function getDateRange(): array
    {
        return match ($this->filter) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week'  => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'prev_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'year'  => [now()->startOfYear(), now()->endOfYear()],
            'custom' => $this->startDate && $this->endDate 
                ? [\Carbon\Carbon::parse($this->startDate)->startOfDay(), \Carbon\Carbon::parse($this->endDate)->endOfDay()] 
                : [now()->startOfDay(), now()->startOfDay()->subSecond()],
            'all'   => [null, null],
            default => [now()->startOfMonth(), now()->endOfMonth()],
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