<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\ParsesGlobalFilters;
use App\Models\Customer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopCustomers extends BaseWidget
{
    use InteractsWithPageFilters;
    use ParsesGlobalFilters;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 12,
        'xl' => 6,
    ];

    public function table(Table $table): Table
    {
        [$startDate, $endDate] = $this->parseFilterDates();

        $query = Customer::query()
            ->select(
                'bl_customers_t.id',
                'bl_customers_t.nama',
                DB::raw('COUNT(bl_invoices_t.id) as total_invoices'),
                DB::raw('SUM(bl_invoices_t.grand_total) as total_spend')
            )
            ->join('bl_invoices_t', 'bl_customers_t.id', '=', 'bl_invoices_t.customer_id')
            ->where('bl_invoices_t.status', 'paid');

        if ($startDate && $endDate) {
            if ($startDate->gt($endDate)) {
                [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
            }
            $query->whereBetween('bl_invoices_t.issued_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('bl_invoices_t.issued_date', '>=', $startDate);
        } elseif ($endDate) {
            $query->where('bl_invoices_t.issued_date', '<=', $endDate);
        }

        $query->groupBy(
            'bl_customers_t.id',
            'bl_customers_t.nama'
        )
            ->orderByDesc('total_spend')
            ->limit(10);

        return $table
            ->heading('Top Customers')
            ->description('Top 10 Customers')
            ->query($query)
            ->defaultSort('total_spend', 'desc')
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
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state ?? 0, 0, ',', '.'))
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
