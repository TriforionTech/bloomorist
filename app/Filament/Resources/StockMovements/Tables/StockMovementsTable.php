<?php

namespace App\Filament\Resources\StockMovements\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),
                TextColumn::make('created_at')
                    ->label('DATE')
                    ->dateTime('d M Y H:i')
                    ->description(fn ($record) => $record->updated_at->diffForHumans())
                    ->sortable(),
                TextColumn::make('product.nama')
                    ->label('PRODUCT')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('TYPE')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in' => 'STOCK IN',
                        'out' => 'STOCK OUT',
                        'sale' => 'SALE',
                        'return' => 'RETURN',
                        'opname' => 'OPNAME',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        'sale' => 'warning',
                        'return' => 'info',
                        'opname' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('QTY')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reference_id')
                    ->label('REFERENCE')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('notes')
                    ->label('NOTES')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->notes)
                    ->placeholder('-'),
                TextColumn::make('user.name')
                    ->label('ADMIN')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Movement Type')
                    ->multiple()
                    ->options([
                        'in' => 'Stock In',
                        'out' => 'Stock Out',
                        'sale' => 'Sale',
                        'return' => 'Return',
                        'opname' => 'Opname',
                    ]),
                Filter::make('time_preset')
                    ->label('Time Range')
                    ->schema([
                        \Filament\Forms\Components\Select::make('preset')
                            ->label('Quick Filter')
                            ->options([
                                'today'      => 'Today',
                                'this_week'  => 'This Week',
                                'this_month' => 'This Month',
                                'last_month' => 'Last Month',
                            ])
                            ->placeholder('All Time')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $preset = $data['preset'] ?? null;
                        return match ($preset) {
                            'today'      => $query->whereDate('created_at', \Carbon\Carbon::today()),
                            'this_week'  => $query->whereBetween('created_at', [\Carbon\Carbon::now()->startOfWeek(), \Carbon\Carbon::now()->endOfWeek()]),
                            'this_month' => $query->whereMonth('created_at', \Carbon\Carbon::now()->month)->whereYear('created_at', \Carbon\Carbon::now()->year),
                            'last_month' => $query->whereMonth('created_at', \Carbon\Carbon::now()->subMonth()->month)->whereYear('created_at', \Carbon\Carbon::now()->subMonth()->year),
                            default      => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): array {
                        $preset = $data['preset'] ?? null;
                        if (!$preset) return [];
                        return ['preset' => 'Period: ' . match ($preset) {
                            'today'      => 'Today',
                            'this_week'  => 'This Week',
                            'this_month' => 'This Month',
                            'last_month' => 'Last Month',
                            default      => $preset,
                        }];
                    }),
                Filter::make('date_range')
                    ->label('Custom Range')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])->schema([
                            DatePicker::make('from')
                                ->label('From Date'),
                            DatePicker::make('until')
                                ->label('Until Date'),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'From: ' . \Carbon\Carbon::parse($data['from'])->format('d M Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Until: ' . \Carbon\Carbon::parse($data['until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->filtersFormWidth('xl');
    }
}