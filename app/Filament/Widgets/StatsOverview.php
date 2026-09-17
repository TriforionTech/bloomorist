<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Product;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Filament\Traits\ParsesGlobalFilters;
use Illuminate\Support\HtmlString;

class StatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;
    use ParsesGlobalFilters;

    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 12,
    ];

    protected function getColumns(): int | array
    {
        return [
            'default' => 1,
            'sm' => 2,
            'md' => 3,
            'lg' => 3,
            'xl' => 5,
        ];
    }

    protected function getStats(): array
    {
        $today = Carbon::today();

        // --- Ambil tanggal dari Global Filter ---
        [$startDate, $endDate, $preset] = $this->parseFilterDates();

        // --- Pendapatan Hari Ini (selalu hari ini, tidak dipengaruhi filter) ---
        $paidTodayQuery = Invoice::where('status', 'paid')->whereDate('issued_date', $today);
        $revenueToday = $paidTodayQuery->sum('grand_total');
        $invoiceTodayPaidCount = $paidTodayQuery->count();

        // --- Pendapatan Berdasarkan Filter ---
        $paidFilterQuery = Invoice::where('status', 'paid');
        if ($startDate && $endDate) {
            $paidFilterQuery->whereBetween('issued_date', [$startDate, $endDate]);
        }
        $revenueFiltered = $paidFilterQuery->sum('grand_total');
        $invoiceFilteredPaidCount = $paidFilterQuery->count();

        // --- Total Order Hari Ini (semua status) ---
        $totalOrdersToday = Invoice::whereDate('issued_date', $today)->count();

        // --- Order Pending ---
        $pendingOrders = Invoice::where('status', 'pending')->count();
        $lowStockProducts = Product::where('is_active', true)->where('stok', '<', 10)->count();

        $filterLabel = 'Pendapatan ' . match($preset) {
            'today' => 'Hari Ini',
            'yesterday' => 'Kemarin',
            'this_month' => 'Bulan Ini',
            'previous_month' => 'Bulan Lalu',
            'ytd' => 'Tahun Ini',
            'previous_year' => 'Tahun Lalu',
            'all' => 'Semua Waktu',
            'custom' => 'Custom Range',
            default => 'Bulan Ini',
        };

        return [
            Stat::make('Total Order Hari Ini', new HtmlString('<span class="text-lg font-bold">' . $totalOrdersToday . '</span>'))
                ->description('Jumlah pesanan yang masuk hari ini')
                ->icon('heroicon-o-document-text')
                ->color('info'),

            Stat::make('Pendapatan Hari Ini', new HtmlString('<span class="text-lg font-bold">Rp ' . number_format($revenueToday, 0, ',', '.') . '</span>'))
                ->description("Total dari {$invoiceTodayPaidCount} invoice lunas hari ini")
                ->icon('heroicon-o-arrow-trending-up')
                ->color('success'),

            Stat::make($filterLabel, new HtmlString('<span class="text-lg font-bold">Rp ' . number_format($revenueFiltered, 0, ',', '.') . '</span>'))
                ->description("Total dari {$invoiceFilteredPaidCount} invoice lunas")
                ->icon('heroicon-o-banknotes')
                ->color('primary'),

            Stat::make('Order Pending', new HtmlString('<span class="text-lg font-bold">' . $pendingOrders . '</span>'))
                ->description('Menunggu pembayaran')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Stok Menipis', new HtmlString('<span class="text-lg font-bold">' . $lowStockProducts . '</span>'))
                ->description('Produk dengan stok kurang dari 10')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
