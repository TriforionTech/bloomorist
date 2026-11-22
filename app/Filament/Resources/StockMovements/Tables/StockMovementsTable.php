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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'user']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),
                TextColumn::make('created_at')
                    ->label('TANGGAL')
                    ->dateTime('d M Y H:i')
                    ->description(fn ($record) => $record->updated_at->diffForHumans())
                    ->sortable(),
                TextColumn::make('product.nama')
                    ->label('PRODUK')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('TIPE')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in' => 'STOK MASUK',
                        'out' => 'STOK KELUAR',
                        'sale' => 'PENJUALAN',
                        'return' => 'RETUR',
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
                    ->label('JUMLAH')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reference_id')
                    ->label('REFERENSI')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('notes')
                    ->label('CATATAN')
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
                    ->label('Tipe Pergerakan')
                    ->multiple()
                    ->options([
                        'in' => 'Stok Masuk',
                        'out' => 'Stok Keluar',
                        'sale' => 'Penjualan',
                        'return' => 'Retur',
                        'opname' => 'Opname',
                    ]),
                Filter::make('time_preset')
                    ->label('Rentang Waktu')
                    ->default(['preset' => 'today'])
                    ->schema([
                        \Filament\Forms\Components\Select::make('preset')
                            ->label('Filter Cepat')
                            ->options([
                                'today' => 'Hari Ini',
                                'this_week' => 'Minggu Ini',
                                'this_month' => 'Bulan Ini',
                                'last_month' => 'Bulan Lalu',
                            ])
                            ->default('today')
                            ->placeholder('Semua Waktu')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $preset = $data['preset'] ?? null;

                        return match ($preset) {
                            'today' => $query->whereDate('created_at', \Carbon\Carbon::today()),
                            'this_week' => $query->whereBetween('created_at', [\Carbon\Carbon::now()->startOfWeek(), \Carbon\Carbon::now()->endOfWeek()]),
                            'this_month' => $query->whereMonth('created_at', \Carbon\Carbon::now()->month)->whereYear('created_at', \Carbon\Carbon::now()->year),
                            'last_month' => $query->whereMonth('created_at', \Carbon\Carbon::now()->subMonth()->month)->whereYear('created_at', \Carbon\Carbon::now()->subMonth()->year),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): array {
                        $preset = $data['preset'] ?? null;
                        if (! $preset) {
                            return [];
                        }

                        return ['preset' => 'Periode: '.match ($preset) {
                            'today' => 'Hari Ini',
                            'this_week' => 'Minggu Ini',
                            'this_month' => 'Bulan Ini',
                            'last_month' => 'Bulan Lalu',
                            default => $preset,
                        }];
                    }),
                Filter::make('date_range')
                    ->label('Rentang Kustom')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])->schema([
                            DatePicker::make('from')
                                ->label('Dari Tanggal'),
                            DatePicker::make('until')
                                ->label('Sampai Tanggal'),
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
                            $indicators['from'] = 'Dari: '.\Carbon\Carbon::parse($data['from'])->format('d M Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Sampai: '.\Carbon\Carbon::parse($data['until'])->format('d M Y');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersFormWidth('xl');
    }
}
