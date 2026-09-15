<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Carbon\Carbon;
use Filament\Tables\Filters\Filter;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopCustomers extends BaseWidget
{
    protected static ?int $sort = 5;
    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 12,
        'xl' => 6,
    ];

    // Empty out the leftover code

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top Customers')
            ->description('Top 10 Customers')
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
                    ->orderByDesc('total_spend')
                    ->limit(10)
            )
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
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),
            ])
                        ->filters([
                Filter::make('date_filter')
                    ->form([
                        \Filament\Forms\Components\Select::make('filter_preset')
                            ->label('Filter By')
                            ->options([
                                'today' => 'Today',
                                'last_7'  => 'Last 7 Days',
                                'this_month' => 'This Month',
                                'previous_month' => 'Previous Month',
                                'this_year'  => 'This Year',
                                'all'   => 'All Time',
                                'custom' => 'Custom Range',
                            ])
                            ->default('all')
                            ->live(),
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('date_from')
                                    ->label('From')
                                    ->displayFormat('d M Y')
                                    ->native(false)
                                    ->live(),
                                DatePicker::make('date_until')
                                    ->label('Until')
                                    ->displayFormat('d M Y')
                                    ->native(false)
                                    ->live(),
                            ])
                            ->visible(fn (Get $get) => $get('filter_preset') === 'custom'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $preset = $data['filter_preset'] ?? 'all';
                        $startDate = null;
                        $endDate = null;

                        if ($preset === 'custom' && !empty($data['date_from']) && !empty($data['date_until'])) {
                            $startDate = Carbon::parse($data['date_from'])->startOfDay();
                            $endDate = Carbon::parse($data['date_until'])->endOfDay();
                        } else {
                            match ($preset) {
                                'today' => [$startDate, $endDate] = [now()->startOfDay(), now()->endOfDay()],
                                'last_7'  => [$startDate, $endDate] = [now()->subDays(6)->startOfDay(), now()->endOfDay()],
                                'this_month' => [$startDate, $endDate] = [now()->startOfMonth(), now()->endOfMonth()],
                                'previous_month' => [$startDate, $endDate] = [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
                                'this_year'  => [$startDate, $endDate] = [now()->startOfYear(), now()->endOfYear()],
                                'all'   => [$startDate, $endDate] = [null, null],
                                default => [$startDate, $endDate] = [null, null],
                            };
                        }

                        if ($startDate && $endDate) {
                            $query->whereBetween('bl_invoices_t.issued_date', [$startDate, $endDate]);
                        }
                    })
            ])
            ->paginated(false);
    }
}
