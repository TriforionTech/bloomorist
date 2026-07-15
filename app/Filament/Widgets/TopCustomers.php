<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopCustomers extends BaseWidget
{
    protected static ?int $sort = 6;
    protected int|string|array $columnSpan = 1;

    public string $timeRange = 'all';

    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('filterTime')
                ->label(match ($this->timeRange) {
                    'today'      => 'Today',
                    'last_7'     => 'Last 7 Days',
                    'this_month' => 'This Month',
                    'this_year'  => 'This Year',
                    default      => 'All Time',
                })
                ->icon('heroicon-o-funnel')
                ->color('gray')
                ->size('sm')
                ->action(function () {
                    $this->timeRange = match ($this->timeRange) {
                        'all'        => 'today',
                        'today'      => 'last_7',
                        'last_7'     => 'this_month',
                        'this_month' => 'this_year',
                        'this_year'  => 'all',
                        default      => 'all',
                    };
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top Customers')
            ->description(match ($this->timeRange) {
                'today'      => 'Top 10 customer — Hari Ini',
                'last_7'     => 'Top 10 customer — 7 Hari Terakhir',
                'this_month' => 'Top 10 customer — Bulan Ini',
                'this_year'  => 'Top 10 customer — Tahun Ini',
                default      => 'Top 10 customer — Semua Waktu',
            })
            ->query(
                Customer::query()
                    ->select(
                        'bl_customers_t.id',
                        'bl_customers_t.nama',
                        DB::raw('COUNT(bl_invoices_t.id) as total_invoices'),
                        DB::raw('SUM(bl_invoices_t.grand_total) as total_spend')
                    )
                    ->join('bl_invoices_t', 'bl_customers_t.id', '=', 'bl_invoices_t.customer_id')
                    ->where('bl_invoices_t.status', 'paid')
                    ->when($this->timeRange === 'today', fn ($q) =>
                        $q->whereDate('bl_invoices_t.issued_date', Carbon::today())
                    )
                    ->when($this->timeRange === 'last_7', fn ($q) =>
                        $q->whereDate('bl_invoices_t.issued_date', '>=', Carbon::now()->subDays(7))
                    )
                    ->when($this->timeRange === 'this_month', fn ($q) =>
                        $q->whereMonth('bl_invoices_t.issued_date', Carbon::now()->month)
                           ->whereYear('bl_invoices_t.issued_date', Carbon::now()->year)
                    )
                    ->when($this->timeRange === 'this_year', fn ($q) =>
                        $q->whereYear('bl_invoices_t.issued_date', Carbon::now()->year)
                    )
                    ->groupBy(
                        'bl_customers_t.id',
                        'bl_customers_t.nama'
                    )
                    ->limit(10)
            )
            ->defaultSort('total_spend', 'desc')
            ->extraAttributes([
                'class' => 'fi-fixed-table',
            ])
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama Customer')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_invoices')
                    ->label('Total Invoice')
                    ->badge()
                    ->color('primary')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_spend')
                    ->label('Total Belanja')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
