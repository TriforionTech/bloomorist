<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopCustomers extends BaseWidget
{
    protected static ?int $sort = 6;
    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top Customers')
            ->description('Top 10 customer berdasarkan total belanja (invoice lunas)')
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
