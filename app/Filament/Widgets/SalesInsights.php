<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SalesInsights extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 1;
    protected ?string $heading = 'Sales Insights';

    protected function getColumns(): int | array | null
    {
        return 2; // 2x2 grid di dalam half-width widget
    }

    protected function getStats(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();

        // ── Base query ──────────────────────────────────────────────────
        $paidMonthInvoices = Invoice::where('status', 'paid')
            ->whereBetween('issued_date', [$startOfMonth, $endOfMonth]);

        $totalRevenueMonth = (clone $paidMonthInvoices)->sum('grand_total');
        $totalOrdersMonth  = (clone $paidMonthInvoices)->count();

        // ── 1. Average Order Value (AOV) ─────────────────────────────────
        $aov = $totalOrdersMonth > 0
            ? (int) round($totalRevenueMonth / $totalOrdersMonth)
            : 0;

        // ── 2. Total Qty Produk Terjual Bulan Ini ────────────────────────
        $totalQtySold = InvoiceItem::query()
            ->join('bl_invoices_t', 'bl_invoice_items_t.invoice_id', '=', 'bl_invoices_t.id')
            ->where('bl_invoices_t.status', 'paid')
            ->whereBetween('bl_invoices_t.issued_date', [$startOfMonth, $endOfMonth])
            ->whereNotIn('snapshot_name', ['Box', 'Wrapping'])
            ->sum('bl_invoice_items_t.quantity');

        // ── 3. Produk Penjualan Tertinggi Bulan Ini ──────────────────────
        $topProduct = InvoiceItem::query()
            ->select('snapshot_name', DB::raw('SUM(bl_invoice_items_t.quantity) as total_qty'))
            ->join('bl_invoices_t', 'bl_invoice_items_t.invoice_id', '=', 'bl_invoices_t.id')
            ->where('bl_invoices_t.status', 'paid')
            ->whereBetween('bl_invoices_t.issued_date', [$startOfMonth, $endOfMonth])
            ->whereNotIn('snapshot_name', ['Box', 'Wrapping'])
            ->groupBy('snapshot_name')
            ->orderByDesc('total_qty')
            ->first();

        $topProductName = $topProduct ? $topProduct->snapshot_name : 'N/A';
        $topProductQty  = $topProduct ? (int) $topProduct->total_qty : 0;

        // ── 4. % Invoice Paid dari Total Invoice ────────────────────────
        $totalInvoices = Invoice::count();
        $totalPaid     = Invoice::where('status', 'paid')->count();
        $paidPercent   = $totalInvoices > 0
            ? round(($totalPaid / $totalInvoices) * 100, 1)
            : 0;

        return [
            Stat::make('AOV Bulan Ini', new \Illuminate\Support\HtmlString('<span class="text-xl sm:text-2xl font-bold">Rp ' . number_format($aov, 0, ',', '.') . '</span>'))
                ->description('Rata-rata nilai per order (paid)')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('primary'),

            Stat::make('Produk Terjual Bulan Ini', new \Illuminate\Support\HtmlString('<span class="text-xl sm:text-2xl font-bold">' . number_format($totalQtySold, 0, ',', '.') . ' pcs</span>'))
                ->description('Total qty dari invoice lunas')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('success'),

            Stat::make('Produk Terlaris Bulan Ini', new \Illuminate\Support\HtmlString('<span class="text-xl sm:text-2xl font-bold">' . $topProductName . '</span>'))
                ->description("{$topProductQty} pcs terjual")
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),

            Stat::make('Invoice Paid', new \Illuminate\Support\HtmlString('<span class="text-xl sm:text-2xl font-bold">' . $paidPercent . '%</span>'))
                ->description("{$totalPaid} dari {$totalInvoices} invoice lunas")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
