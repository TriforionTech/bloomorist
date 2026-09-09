<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class SalesInsights extends BaseWidget
{
    public ?string $monthFilter = null;

    public function mount(): void
    {
        $this->monthFilter = now()->format('Y-m');
    }

    #[On('month-filter-updated')]
    public function updateMonthFilter($month): void
    {
        $this->monthFilter = $month;
    }

    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 4;
    protected ?string $heading = 'Sales Insights';

    protected function getColumns(): int | array | null
    {
        return 1; // 1 kolom vertikal
    }

    protected function getStats(): array
    {
        // --- Ambil bulan dari filter (atau default) ---
        $monthFilter = $this->monthFilter ?? now()->format('Y-m');
        $filterDate = Carbon::createFromFormat('Y-m', $monthFilter)->startOfMonth();
        $startOfMonth = $filterDate->copy()->startOfMonth();
        $endOfMonth   = $filterDate->copy()->endOfMonth();

        $monthLabel = $filterDate->translatedFormat('F Y');

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

        // ── 4. % Invoice Paid dari Total Invoice (Bulan Ini) ────────────────────────
        $totalInvoices = Invoice::whereBetween('issued_date', [$startOfMonth, $endOfMonth])->count();
        $totalPaid     = Invoice::where('status', 'paid')
            ->whereBetween('issued_date', [$startOfMonth, $endOfMonth])
            ->count();
        $paidPercent   = $totalInvoices > 0
            ? round(($totalPaid / $totalInvoices) * 100, 1)
            : 0;

        return [
            Stat::make("AOV {$monthLabel}", 'Rp ' . number_format($aov, 0, ',', '.'))
                ->description('Rata-rata nilai per order (paid)')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('primary')
                ->extraAttributes(['class' => 'fi-compact-stat']),

            Stat::make("Produk Terjual {$monthLabel}", number_format($totalQtySold, 0, ',', '.') . ' pcs')
                ->description('Total qty dari invoice lunas')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('success')
                ->extraAttributes(['class' => 'fi-compact-stat']),

            Stat::make("Produk Terlaris {$monthLabel}", $topProductName)
                ->description("{$topProductQty} pcs terjual")
                ->descriptionIcon('heroicon-m-star')
                ->color('warning')
                ->extraAttributes(['class' => 'fi-compact-stat']),

            Stat::make('Invoice Paid', $paidPercent . '%')
                ->description("{$totalPaid} dari {$totalInvoices} invoice lunas")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->extraAttributes(['class' => 'fi-compact-stat']),
        ];
    }
}
