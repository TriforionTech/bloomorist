<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Product;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

use Illuminate\Support\Facades\Blade;

class StatsOverview extends BaseWidget
{
    public ?string $monthFilter = null;

    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 12;

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

    public function mount(): void
    {
        $this->monthFilter = now()->format('Y-m');
    }

    public function updatedMonthFilter($value): void
    {
        $this->dispatch('month-filter-updated', month: $value);
    }

    public function previousMonth(): void
    {
        $date = Carbon::createFromFormat('Y-m', $this->monthFilter)->subMonth();
        $this->monthFilter = $date->format('Y-m');
        $this->dispatch('month-filter-updated', month: $this->monthFilter);
    }

    public function nextMonth(): void
    {
        $date = Carbon::createFromFormat('Y-m', $this->monthFilter)->addMonth();
        if ($date->copy()->startOfMonth()->lte(Carbon::now()->startOfMonth())) {
            $this->monthFilter = $date->format('Y-m');
            $this->dispatch('month-filter-updated', month: $this->monthFilter);
        }
    }

    protected function getStats(): array
    {
        $today = Carbon::today();

        // --- Ambil bulan dari filter (atau default) ---
        $monthFilter = $this->monthFilter ?? now()->format('Y-m');
        $filterDate = Carbon::createFromFormat('Y-m', $monthFilter)->startOfMonth();
        $startOfMonth = $filterDate->copy()->startOfMonth();
        $endOfMonth = $filterDate->copy()->endOfMonth();

        // --- Pendapatan Hari Ini (selalu hari ini, tidak dipengaruhi filter) ---
        $paidTodayQuery = Invoice::where('status', 'paid')->whereDate('issued_date', $today);
        $revenueToday = $paidTodayQuery->sum('grand_total');
        $invoiceTodayPaidCount = $paidTodayQuery->count();

        // --- Pendapatan Bulan (sesuai filter) ---
        $paidMonthQuery = Invoice::where('status', 'paid')->whereBetween('issued_date', [$startOfMonth, $endOfMonth]);
        $revenueThisMonth = $paidMonthQuery->sum('grand_total');
        $invoiceMonthPaidCount = $paidMonthQuery->count();

        // --- Total Order Hari Ini (semua status) ---
        $totalOrdersToday = Invoice::whereDate('created_at', $today)->count();

        // --- Order Pending ---
        $pendingOrders = Invoice::where('status', 'pending')->count();

        // --- Stok Menipis (< 10, hanya produk aktif) ---
        $lowStockProducts = Product::where('is_active', true)->where('stok', '<', 10)->count();

        // --- Simple HTML Navigation Filter < JUN > ---
        $monthShort = $filterDate->translatedFormat('M'); // 3 huruf, misal "Sep"
        $isCurrentMonth = $filterDate->isSameMonth(Carbon::now());
        $nextButtonDisabled = $isCurrentMonth ? 'opacity-30 cursor-not-allowed' : 'hover:text-primary-600 dark:hover:text-primary-400';

        $monthNavHtml = '
            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                <button wire:click.stop="previousMonth" class="transition hover:text-primary-600 dark:hover:text-primary-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <span class="text-sm font-bold uppercase tracking-wider text-gray-800 dark:text-gray-200">' . $monthShort . '</span>
                <button wire:click.stop="nextMonth" class="transition ' . $nextButtonDisabled . '" ' . ($isCurrentMonth ? 'disabled' : '') . '>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
        ';

        return [
            Stat::make('Total Order Hari Ini', $totalOrdersToday)
                ->description('Jumlah pesanan yang masuk hari ini')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->view('filament.widgets.custom-stat'),

            Stat::make('Pendapatan Hari Ini', 'Rp ' . number_format($revenueToday, 0, ',', '.'))
                ->description("Total dari {$invoiceTodayPaidCount} invoice lunas hari ini")
                ->icon('heroicon-o-arrow-trending-up')
                ->color('success')
                ->view('filament.widgets.custom-stat'),

            Stat::make('Pendapatan Bulanan', 'Rp ' . number_format($revenueThisMonth, 0, ',', '.'))
                ->description("Total dari {$invoiceMonthPaidCount} invoice lunas")
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->extraAttributes(['month-nav' => $monthNavHtml])
                ->view('filament.widgets.custom-stat'),

            Stat::make('Order Pending', $pendingOrders)
                ->description('Menunggu pembayaran')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->view('filament.widgets.custom-stat'),

            Stat::make('Stok Menipis', $lowStockProducts)
                ->description('Produk dengan stok kurang dari 10')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->view('filament.widgets.custom-stat'),
        ];
    }
}
