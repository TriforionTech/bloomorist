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
                // Read directly from the Livewire component's public property to ensure we have the live, un-cached state
                $filterState = $this->tableFilters['date_filter'] ?? [];
                
                $preset = $filterState['filter_preset'] ?? request()->query('filter', 'this_month');
                
                $startDate = null;
                $endDate = null;

                if ($preset === 'custom' && !empty($filterState['date_from']) && !empty($filterState['date_until'])) {
                    $startDate = Carbon::parse($filterState['date_from'])->startOfDay();
                    $endDate = Carbon::parse($filterState['date_until'])->endOfDay();
                } else {
                    match ($preset) {
                        'today' => [$startDate, $endDate] = [now()->startOfDay(), now()->endOfDay()],
                        'yesterday' => [$startDate, $endDate] = [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
                        'this_month' => [$startDate, $endDate] = [now()->startOfMonth(), now()->endOfMonth()],
                        'previous_month' => [$startDate, $endDate] = [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
                        'ytd'  => [$startDate, $endDate] = [now()->startOfYear(), now()->endOfDay()],
                        'previous_year' => [$startDate, $endDate] = [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
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
                        AND {$invoiceTable}.issued_date BETWEEN ? AND ?
                    ), 0) as total_sold", [$startDate, $endDate]);

                    $query->selectRaw("COALESCE((
                        SELECT SUM({$invoiceItemTable}.discount_price) 
                        FROM {$invoiceItemTable} 
                        JOIN {$invoiceTable} ON {$invoiceTable}.id = {$invoiceItemTable}.invoice_id 
                        WHERE {$invoiceItemTable}.product_id = bl_products_t.id 
                        AND {$invoiceTable}.status = 'paid' 
                        AND {$invoiceTable}.issued_date BETWEEN ? AND ?
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
                    ->rowIndex()
                    ->visibleFrom('md'),
                TextColumn::make('nama')
                    ->label('PRODUCT')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),
                TextColumn::make('total_sold')
                    ->label('QTY')
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
                                'yesterday' => 'Yesterday',
                                'this_month' => 'This Month',
                                'previous_month' => 'Previous Month',
                                'ytd' => 'This Year',
                                'previous_year' => 'Previous Year',
                                'all'   => 'All Time',
                                'custom' => 'Custom Range',
                            ])
                            ->default(fn () => request()->query('filter', 'this_month'))
                            ->live(),
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('date_from')
                                    ->label('From')
                                    ->displayFormat('d M Y')
                                    ->native(false)
                                    ->live(), // Added live to fix responsiveness
                                DatePicker::make('date_until')
                                    ->label('Until')
                                    ->displayFormat('d M Y')
                                    ->native(false)
                                    ->live(), // Added live to fix responsiveness
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
                            $indicators['preset'] = 'Period: ' . match($data['filter_preset'] ?? 'this_month') {
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'this_month' => 'This Month',
                                'previous_month' => 'Previous Month',
                                'ytd' => 'This Year',
                                'previous_year' => 'Previous Year',
                                'all' => 'All Time',
                                'custom' => 'Custom Range',
                                default => 'This Month'
                            };
                        }
                        return $indicators;
                    })
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action->label('Filter Period')->extraAttributes(['class' => 'mr-3 sm:mr-0']),
            );
    }
}
