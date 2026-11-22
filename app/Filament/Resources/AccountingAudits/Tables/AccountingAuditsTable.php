<?php

namespace App\Filament\Resources\AccountingAudits\Tables;

use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountingAuditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'journal']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('WAKTU')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('PENGGUNA')
                    ->placeholder('Sistem')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('action')
                    ->label('AKSI')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->sortable(),

                TextColumn::make('display_no_bukti')
                    ->label('NO. BUKTI')
                    ->weight('bold')
                    ->color('primary')
                    ->copyable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $subQuery) use ($search) {
                            $subQuery->whereHas('journal', fn (Builder $jq) => $jq->where('no_bukti', 'like', "%{$search}%"))
                                ->orWhere('old_values', 'like', "%{$search}%")
                                ->orWhere('new_values', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('display_keterangan')
                    ->label('KETERANGAN')
                    ->limit(50)
                    ->tooltip(fn ($record): string => (string) $record->display_keterangan)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $subQuery) use ($search) {
                            $subQuery->whereHas('journal', fn (Builder $jq) => $jq->where('keterangan', 'like', "%{$search}%"))
                                ->orWhere('old_values', 'like', "%{$search}%")
                                ->orWhere('new_values', 'like', "%{$search}%");
                        });
                    }),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Jenis Aksi')
                    ->options([
                        'created' => 'CREATED (Dibuat)',
                        'updated' => 'UPDATED (Diubah)',
                        'deleted' => 'DELETED (Dihapus)',
                    ]),

                SelectFilter::make('user_id')
                    ->label('Pengguna')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('created_at')
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
                                fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)
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
            ->recordActionsColumnLabel('AKSI')
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat Detail')
                    ->modalHeading('Detail Log Audit Akuntansi')
                    ->modalWidth('4xl'),
            ]);
    }
}
