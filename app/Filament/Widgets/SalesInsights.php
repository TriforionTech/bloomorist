<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Illuminate\Support\HtmlString;

class SalesInsights extends BaseWidget
{
    use \Filament\Widgets\Concerns\InteractsWithPageFilters;
    use \App\Filament\Traits\ParsesGlobalFilters;

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
        [$start, $end, $preset] = $this->parseFilterDates();
        $emptyCustom = ($preset === 'custom' && (!$start || !$end));

        $label = match($preset) {
            'today' => 'Hari Ini',
            'yesterday' => 'Kemarin',
            'this_month' => 'Bulan Ini',
            'previous_month' => 'Bulan Lalu',
            'ytd' => 'Tahun Ini',
            'previous_year' => 'Tahun Lalu',
            'all' => 'Semua Waktu',
            'custom' => $emptyCustom ? 'Pilih Tanggal' : ($start?->format('d M') . ' - ' . $end?->format('d M')),
            default => 'Bulan Ini',
        };

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
            Stat::make("AOV {$label}", new HtmlString('<span class="text-lg font-bold">Rp ' . number_format($aov, 0, ',', '.') . '</span>'))
                ->description('Rata-rata nilai per order (paid)')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('primary'),

            Stat::make("Produk Terjual {$label}", new HtmlString('<span class="text-lg font-bold">' . number_format($totalQtySold, 0, ',', '.') . ' pcs</span>'))
                ->description('Total qty dari invoice lunas')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('success'),

            Stat::make("Produk Terlaris {$label}", new HtmlString('<span class="text-lg font-bold">' . $topProductName . '</span>'))
                ->description("{$topProductQty} pcs terjual")
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),

            Stat::make('Invoice Paid', new HtmlString('<span class="text-lg font-bold">' . $paidPercent . '%</span>'))
                ->description("{$totalPaid} dari {$totalInvoices} invoice lunas")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
