<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Product;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // --- Pendapatan Hari Ini ---
        $paidTodayQuery = Invoice::where('status', 'paid')->whereDate('issued_date', $today);
        $revenueToday = $paidTodayQuery->sum('grand_total');
        $invoiceTodayPaidCount = $paidTodayQuery->count();

        // --- Pendapatan Bulan Ini ---
        $paidMonthQuery = Invoice::where('status', 'paid')->whereBetween('issued_date', [$startOfMonth, $endOfMonth]);
        $revenueThisMonth = $paidMonthQuery->sum('grand_total');
        $invoiceMonthPaidCount = $paidMonthQuery->count();

        // --- Total Order Hari Ini (semua status) ---
        $totalOrdersToday = Invoice::whereDate('created_at', $today)->count();

        // --- Order Pending ---
        $pendingOrders = Invoice::where('status', 'pending')->count();

        // --- Stok Menipis (< 10, hanya produk aktif) ---
        $lowStockProducts = Product::where('is_active', true)->where('stok', '<', 10)->count();

        return [
            Stat::make('Pendapatan Hari Ini', new \Illuminate\Support\HtmlString('<span class="text-2xl font-bold">Rp ' . number_format($revenueToday, 0, ',', '.') . '</span>'))
                ->description("Total dari {$invoiceTodayPaidCount} invoice lunas hari ini")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Pendapatan Bulan Ini', new \Illuminate\Support\HtmlString('<span class="text-2xl font-bold">Rp ' . number_format($revenueThisMonth, 0, ',', '.') . '</span>'))
                ->description("Total dari {$invoiceMonthPaidCount} invoice lunas bulan ini")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Total Order Hari Ini', new \Illuminate\Support\HtmlString('<span class="text-2xl font-bold">' . $totalOrdersToday . '</span>'))
                ->description('Jumlah pesanan yang masuk hari ini')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            Stat::make('Order Pending', new \Illuminate\Support\HtmlString('<span class="text-2xl font-bold">' . $pendingOrders . '</span>'))
                ->description('Menunggu pembayaran')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Stok Menipis', new \Illuminate\Support\HtmlString('<span class="text-2xl font-bold">' . $lowStockProducts . '</span>'))
                ->description('Produk dengan stok kurang dari 10')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
