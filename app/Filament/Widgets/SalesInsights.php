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
    public ?string $filter = 'month';
    public ?string $startDate = null;
    public ?string $endDate = null;

    #[On('sales-chart-filter-updated')]
    public function updateFilter($filter, $startDate = null, $endDate = null): void
    {
        $this->filter = $filter;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 12,
        'xl' => 4,
    ];
    protected ?string $heading = 'Sales Insights';

    protected function getColumns(): int | array | null
    {
        return 1; // 1 kolom vertikal
    }

    protected function getStats(): array
    {
        $start = null;
        $end = null;
        $label = '';
        $emptyCustom = false;

        if ($this->filter === 'today') {
            $start = Carbon::today()->startOfDay();
            $end = Carbon::today()->endOfDay();
            $label = 'Hari Ini';
        } elseif ($this->filter === 'week') {
            $start = Carbon::today()->subDays(6)->startOfDay();
            $end = Carbon::today()->endOfDay();
            $label = '7 Hari Terakhir';
        } elseif ($this->filter === 'month') {
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
            $label = 'Bulan Ini';
        } elseif ($this->filter === 'prev_month') {
            $start = Carbon::now()->subMonth()->startOfMonth();
            $end = Carbon::now()->subMonth()->endOfMonth();
            $label = 'Bulan Sebelumnya';
        } elseif ($this->filter === 'year') {
            $start = Carbon::now()->startOfYear();
            $end = Carbon::now()->endOfYear();
            $label = 'Tahun Ini';
        } elseif ($this->filter === 'all') {
            $start = null;
            $end = null;
            $label = 'Semua Waktu';
        } elseif ($this->filter === 'custom') {
            if ($this->startDate && $this->endDate) {
                $start = Carbon::parse($this->startDate)->startOfDay();
                $end = Carbon::parse($this->endDate)->endOfDay();
                $label = Carbon::parse($this->startDate)->format('d M') . ' - ' . Carbon::parse($this->endDate)->format('d M');
            } else {
                $emptyCustom = true;
                $label = 'Pilih Tanggal';
            }
        } else {
            // Default to month
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
            $label = 'Bulan Ini';
        }

        // ── Base query ──────────────────────────────────────────────────
        $paidMonthInvoices = Invoice::where('status', 'paid');
        if ($emptyCustom) {
            $paidMonthInvoices->whereRaw('1 = 0');
        } elseif ($start && $end) {
            $paidMonthInvoices->whereBetween('issued_date', [$start, $end]);
        }

        $totalRevenueMonth = (clone $paidMonthInvoices)->sum('grand_total');
        $totalOrdersMonth  = (clone $paidMonthInvoices)->count();

        // ── 1. Average Order Value (AOV) ─────────────────────────────────
        $aov = $totalOrdersMonth > 0
            ? (int) round($totalRevenueMonth / $totalOrdersMonth)
            : 0;

        // ── 2. Total Qty Produk Terjual Bulan Ini ────────────────────────
        $qtyQuery = InvoiceItem::query()
            ->join('bl_invoices_t', 'bl_invoice_items_t.invoice_id', '=', 'bl_invoices_t.id')
            ->where('bl_invoices_t.status', 'paid')
            ->whereNotIn('snapshot_name', ['Box', 'Wrapping']);
            
        if ($emptyCustom) {
            $qtyQuery->whereRaw('1 = 0');
        } elseif ($start && $end) {
            $qtyQuery->whereBetween('bl_invoices_t.issued_date', [$start, $end]);
        }
        $totalQtySold = $qtyQuery->sum('bl_invoice_items_t.quantity');

        // ── 3. Produk Penjualan Tertinggi Bulan Ini ──────────────────────
        $topProductQuery = InvoiceItem::query()
            ->select('snapshot_name', DB::raw('SUM(bl_invoice_items_t.quantity) as total_qty'))
            ->join('bl_invoices_t', 'bl_invoice_items_t.invoice_id', '=', 'bl_invoices_t.id')
            ->where('bl_invoices_t.status', 'paid')
            ->whereNotIn('snapshot_name', ['Box', 'Wrapping']);
            
        if ($emptyCustom) {
            $topProductQuery->whereRaw('1 = 0');
        } elseif ($start && $end) {
            $topProductQuery->whereBetween('bl_invoices_t.issued_date', [$start, $end]);
        }
            
        $topProduct = $topProductQuery->groupBy('snapshot_name')
            ->orderByDesc('total_qty')
            ->first();

        $topProductName = $topProduct ? $topProduct->snapshot_name : 'N/A';
        $topProductQty  = $topProduct ? (int) $topProduct->total_qty : 0;

        // ── 4. % Invoice Paid dari Total Invoice (Bulan Ini) ────────────────────────
        $totalInvoicesQuery = Invoice::query();
        $totalPaidQuery = Invoice::where('status', 'paid');
        
        if ($emptyCustom) {
            $totalInvoicesQuery->whereRaw('1 = 0');
            $totalPaidQuery->whereRaw('1 = 0');
        } elseif ($start && $end) {
            $totalInvoicesQuery->whereBetween('issued_date', [$start, $end]);
            $totalPaidQuery->whereBetween('issued_date', [$start, $end]);
        }
        
        $totalInvoices = $totalInvoicesQuery->count();
        $totalPaid = $totalPaidQuery->count();
        $paidPercent   = $totalInvoices > 0
            ? round(($totalPaid / $totalInvoices) * 100, 1)
            : 0;

        return [
            Stat::make("AOV {$label}", 'Rp ' . number_format($aov, 0, ',', '.'))
                ->description('Rata-rata nilai per order (paid)')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('primary')
                ->extraAttributes(['class' => 'fi-compact-stat']),

            Stat::make("Produk Terjual {$label}", number_format($totalQtySold, 0, ',', '.') . ' pcs')
                ->description('Total qty dari invoice lunas')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('success')
                ->extraAttributes(['class' => 'fi-compact-stat']),

            Stat::make("Produk Terlaris {$label}", $topProductName)
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
