<?php

namespace App\Filament\Resources\StockTransfers\Tables;

use App\Models\StockTransfer;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sourceProduct', 'targetProduct']))
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),

                TextColumn::make('tanggal')
                    ->label('TANGGAL')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('sourceProduct.nama')
                    ->label('DARI PRODUK')
                    ->sortable()
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('targetProduct.nama')
                    ->label('KE PRODUK')
                    ->sortable()
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('quantity')
                    ->label('QTY')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('target_value')
                    ->label('Nilai Hasil Transfer')
                    ->state(function (StockTransfer $record) {
                        $cost = (float) ($record->targetProduct?->harga_beli ?? 0);

                        return 'Rp '.number_format($record->quantity * $cost, 0, ',', '.');
                    })
                    ->color('success')
                    ->weight('bold')
                    ->alignEnd(),

                TextColumn::make('keterangan')
                    ->label('KETERANGAN')
                    ->limit(40)
                    ->tooltip(fn ($record): ?string => $record->keterangan)
                    ->searchable(),
            ])
            ->filters([
                Filter::make('tanggal')
                    ->label('Rentang Tanggal')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])->schema([
                            DatePicker::make('from')->label('Dari Tanggal'),
                            DatePicker::make('until')->label('Sampai Tanggal'),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $q, $date) => $q->whereDate('tanggal', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $q, $date) => $q->whereDate('tanggal', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'Dari: '.Carbon::parse($data['from'])->format('d M Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Sampai: '.Carbon::parse($data['until'])->format('d M Y');
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat Detail')
                    ->modalHeading('Rincian Transfer Stok')
                    ->modalWidth('2xl'),
            ]);
    }
}
