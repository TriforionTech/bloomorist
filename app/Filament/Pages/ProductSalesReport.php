<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\Action;
use BackedEnum;
use UnitEnum;

class ProductSalesReport extends Page implements HasTable
{
    use InteractsWithTable;
    
    protected static ?string $title = 'Sales Report per Product';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static string|UnitEnum|null $navigationGroup = 'Sales & Invoicing';
    protected static ?int $navigationSort = 4;
    
    protected string $view = 'filament.pages.product-sales-report';

    public function mount(): void
    {
        // No manual properties needed, Filament Filter handles the state
    }

    public function table(Table $table): Table
    {
        $productTable = (new Product)->getTable();
        $invoiceItemTable = 'bl_invoice_items_t';
        $invoiceTable = 'bl_invoices_t';

        return $table
            ->query(
                Product::query()
                    ->select("{$productTable}.*")
                    ->whereNotIn("{$productTable}.nama", ['Box', 'Wrapping'])
            )
            ->modifyQueryUsing(function (Builder $query) use ($invoiceTable, $invoiceItemTable) {
                // Get filter state safely, defaulting to 'month'
                $filterState = $this->getTableFilterState('date_filter') ?? [];
                $preset = $filterState['filter_preset'] ?? request()->query('filter', 'month');
                
                $startDate = null;
                $endDate = null;

                if ($preset === 'custom' && !empty($filterState['date_from']) && !empty($filterState['date_until'])) {
                    $startDate = Carbon::parse($filterState['date_from'])->startOfDay();
                    $endDate = Carbon::parse($filterState['date_until'])->endOfDay();
                } else {
                    match ($preset) {
                        'today' => [$startDate, $endDate] = [now()->startOfDay(), now()->endOfDay()],
                        'week'  => [$startDate, $endDate] = [now()->subDays(6)->startOfDay(), now()->endOfDay()],
                        'month' => [$startDate, $endDate] = [now()->startOfMonth(), now()->endOfMonth()],
                        'year'  => [$startDate, $endDate] = [now()->startOfYear(), now()->endOfYear()],
                        'all'   => [$startDate, $endDate] = [null, null],
                        default => [$startDate, $endDate] = [now()->startOfMonth(), now()->endOfMonth()],
                    };
                }

                if ($startDate && $endDate) {
                    $query->selectRaw("COALESCE((
                        SELECT SUM({$invoiceItemTable}.quantity) 
                        FROM {$invoiceItemTable} 
                        JOIN {$invoiceTable} ON {$invoiceTable}.id = {$invoiceItemTable}.invoice_id 
                        WHERE {$invoiceItemTable}.product_id = bl_products_t.id 
                        AND {$invoiceTable}.status = 'paid' 
                        AND {$invoiceTable}.created_at BETWEEN ? AND ?
                    ), 0) as total_sold", [$startDate, $endDate]);

                    $query->selectRaw("COALESCE((
                        SELECT SUM({$invoiceItemTable}.discount_price) 
                        FROM {$invoiceItemTable} 
                        JOIN {$invoiceTable} ON {$invoiceTable}.id = {$invoiceItemTable}.invoice_id 
                        WHERE {$invoiceItemTable}.product_id = bl_products_t.id 
                        AND {$invoiceTable}.status = 'paid' 
                        AND {$invoiceTable}.created_at BETWEEN ? AND ?
                    ), 0) as total_revenue", [$startDate, $endDate]);
                } else {
                    $query->selectRaw("COALESCE((
                        SELECT SUM({$invoiceItemTable}.quantity) 
                        FROM {$invoiceItemTable} 
                        JOIN {$invoiceTable} ON {$invoiceTable}.id = {$invoiceItemTable}.invoice_id 
                        WHERE {$invoiceItemTable}.product_id = bl_products_t.id 
                        AND {$invoiceTable}.status = 'paid'
                    ), 0) as total_sold");

                    $query->selectRaw("COALESCE((
                        SELECT SUM({$invoiceItemTable}.discount_price) 
                        FROM {$invoiceItemTable} 
                        JOIN {$invoiceTable} ON {$invoiceTable}.id = {$invoiceItemTable}.invoice_id 
                        WHERE {$invoiceItemTable}.product_id = bl_products_t.id 
                        AND {$invoiceTable}.status = 'paid'
                    ), 0) as total_revenue");
                }
            })
            ->columns([
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),
                TextColumn::make('nama')
                    ->label('PRODUCT')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('total_sold')
                    ->label('QTY SOLD')
                    ->sortable(query: function (Builder $query, string $direction) {
                        return $query->orderBy('total_sold', $direction)->orderBy('nama', 'asc');
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => number_format($state ?? 0, 0, ',', '.')),
                TextColumn::make('total_revenue')
                    ->label('REVENUE')
                    ->sortable(query: function (Builder $query, string $direction) {
                        return $query->orderBy('total_revenue', $direction)->orderBy('nama', 'asc');
                    })
                    ->weight('bold')
                    ->color('success')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.')),
            ])
            ->defaultSort('total_sold', 'desc')
            ->filters([
                Filter::make('date_filter')
                    ->form([
                        \Filament\Forms\Components\Select::make('filter_preset')
                            ->label('Filter By')
                            ->options([
                                'today' => 'Today',
                                'week'  => 'Last 7 Days',
                                'month' => 'This Month',
                                'year'  => 'This Year',
                                'all'   => 'All Time',
                            ])
                            ->default(fn () => request()->query('filter', 'month')),
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('date_from')
                                    ->label('From')
                                    ->displayFormat('d M Y')
                                    ->native(false),
                                DatePicker::make('date_until')
                                    ->label('Until')
                                    ->displayFormat('d M Y')
                                    ->native(false),
                            ])
                            ->visible(fn (Get $get) => $get('filter_preset') === 'custom'),
                    ])
                    ->query(function (Builder $query) {
                        // Handled in modifyQueryUsing
                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (($data['filter_preset'] ?? 'month') === 'custom') {
                            if ($data['date_from'] ?? null) {
                                $indicators['date_from'] = 'From: ' . Carbon::parse($data['date_from'])->format('d M Y');
                            }
                            if ($data['date_until'] ?? null) {
                                $indicators['date_until'] = 'Until: ' . Carbon::parse($data['date_until'])->format('d M Y');
                            }
                        } else {
                            $indicators['preset'] = 'Period: ' . match($data['filter_preset'] ?? 'month') {
                                'today' => 'Today',
                                'week' => 'Last 7 Days',
                                'month' => 'This Month',
                                'year' => 'This Year',
                                'all' => 'All Time',
                                default => 'This Month'
                            };
                        }
                        return $indicators;
                    })
            ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->filtersTriggerAction(
                fn (Action $action) => $action->label('Filter Period'),
            );
    }
}
