<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopProducts extends ChartWidget
{
    protected ?string $heading = 'Top Selling Products';
    protected ?string $description = 'Berdasarkan total qty terjual dari invoice lunas';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $results = Product::query()
            ->select(
                'bl_products_t.id',
                'bl_products_t.sku',
                'bl_products_t.nama',
                DB::raw('SUM(bl_invoice_items_t.quantity) as total_sold')
            )
            ->join('bl_invoice_items_t', 'bl_products_t.id', '=', 'bl_invoice_items_t.product_id')
            ->join('bl_invoices_t', 'bl_invoice_items_t.invoice_id', '=', 'bl_invoices_t.id')
            ->where('bl_invoices_t.status', 'paid')
            ->whereNotIn('bl_products_t.nama', ['Box', 'Wrapping'])
            ->groupBy('bl_products_t.id') // Safe: PK → aman untuk only_full_group_by
            ->orderByDesc('total_sold')
            ->limit(10)
            ->get();

        // Label: "Nama Produk" — SKU muncul di tooltip via extra dataset metadata
        $labels     = $results->pluck('nama')->toArray();
        $quantities = $results->pluck('total_sold')->map(fn ($v) => (int) $v)->toArray();
        $skus       = $results->pluck('sku')->toArray();

        // Warna gradien pink mengikuti tema Bloomorist
        $colors = array_fill(0, count($results), 'rgba(219, 39, 119, 0.75)');

        return [
            'datasets' => [
                [
                    'label'           => 'Qty Terjual',
                    'data'            => $quantities,
                    'backgroundColor' => $colors,
                    'borderColor'     => array_fill(0, count($results), 'rgba(190, 24, 93, 0.9)'),
                    'borderWidth'     => 1,
                    'skus'            => $skus, // Extra metadata untuk tooltip custom
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis'  => 'y', // Horizontal bar chart
            'responsive' => true,
            'plugins'    => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'callbacks' => [
                        // Tampilkan SKU di tooltip
                        'label' => \Filament\Support\RawJs::make(<<<'JS'
                            function(context) {
                                var sku = context.dataset.skus ? context.dataset.skus[context.dataIndex] : '';
                                return ' ' + context.parsed.x + ' pcs' + (sku ? ' · ' + sku : '');
                            }
                        JS),
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks'       => ['precision' => 0],
                    'grid'        => ['display' => true],
                ],
                'y' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
