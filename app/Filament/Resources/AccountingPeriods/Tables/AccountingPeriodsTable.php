<?php

namespace App\Filament\Resources\AccountingPeriods\Tables;

use App\Models\AccountingPeriod;
use App\Services\ClosingEntryService;
use App\Services\DepreciationService;
use App\Services\PeriodClosingService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccountingPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('label')
                    ->label('NAMA PERIODE')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('start_date')
                    ->label('MULAI')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('SELESAI')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('closing_inventory_value')
                    ->label('STOCK OPNAME')
                    ->formatStateUsing(function ($state, AccountingPeriod $record) {
                        if ($state !== null) {
                            return 'Rp '.number_format($state, 0, ',', '.');
                        }
                        $est = app(PeriodClosingService::class)->calculateClosingInventoryValue($record);

                        return 'Rp '.number_format($est, 0, ',', '.').' (Auto)';
                    })
                    ->color(fn ($state) => $state !== null ? 'success' : 'info')
                    ->tooltip(fn ($state) => $state === null ? 'Otomatis dihitung dari stok fisik saat Tutup Buku' : 'Nilai persediaan akhir terkunci'),

                TextColumn::make('status')
                    ->label('STATUS')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'closed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => '🟢 OPEN',
                        'closed' => '🔴 CLOSED',
                        default => strtoupper($state),
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActionsColumnLabel('AKSI')
            ->recordActions([
                Action::make('jurnalPenutup')
                    ->label('Jurnal Penutup')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Posting Jurnal Penutup')
                    ->modalDescription(fn (AccountingPeriod $record) => "Tutup akun pendapatan dan beban periode {$record->label} ke Modal Pemilik.")
                    ->disabled(fn (AccountingPeriod $record) => $record->isClosed())
                    ->action(function (AccountingPeriod $record) {
                        try {
                            $journal = app(ClosingEntryService::class)->post($record);
                            Notification::make()
                                ->title('Jurnal penutup berhasil diposting')
                                ->body("Jurnal {$journal->no_bukti} berhasil dibuat.")
                                ->success()
                                ->send();
                        } catch (\RuntimeException $exception) {
                            Notification::make()
                                ->title('Gagal posting jurnal penutup')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('tutupBuku')
                    ->label('Tutup Buku')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Tutup Periode Akuntansi')
                    ->modalDescription(function (AccountingPeriod $record) {
                        $info = '';
                        if ($record->closing_inventory_value === null) {
                            $autoVal = app(PeriodClosingService::class)->calculateClosingInventoryValue($record);
                            $info = ' Nilai Stock Opname akhir otomatis dihitung sebesar Rp '.number_format($autoVal, 0, ',', '.').' dari stok fisik saat ini.';
                        } else {
                            $info = ' Nilai Stock Opname akhir: Rp '.number_format((float) $record->closing_inventory_value, 0, ',', '.').'.';
                        }

                        return "Apakah Anda yakin ingin mengunci periode {$record->label}?{$info} Anda TIDAK AKAN BISA menambah atau mengubah transaksi (Invoices, Expenses) di periode ini lagi. Pastikan semua data sudah final.";
                    })
                    ->disabled(fn (AccountingPeriod $record) => $record->isClosed())
                    ->action(function (AccountingPeriod $record) {
                        try {
                            app(PeriodClosingService::class)->close($record);
                            Notification::make()
                                ->title('Periode Berhasil Ditutup')
                                ->body("Periode {$record->label} telah dikunci dan rekap bulanannya dibuat.")
                                ->success()
                                ->send();
                        } catch (\RuntimeException $exception) {
                            Notification::make()
                                ->title('Gagal Tutup Buku')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('postingPenyusutan')
                    ->label('Posting Penyusutan')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Posting Penyusutan Periode Ini')
                    ->modalDescription(fn (AccountingPeriod $record) => "Sistem akan menghitung dan memposting penyusutan sampai {$record->end_date->format('d M Y')}. Posting tidak dapat diulang untuk periode yang sama.")
                    ->disabled(fn (AccountingPeriod $record) => $record->isClosed())
                    ->action(function (AccountingPeriod $record) {
                        try {
                            $journal = app(DepreciationService::class)->postMonthlyDepreciation($record);

                            Notification::make()
                                ->title('Penyusutan berhasil diposting')
                                ->body("Jurnal {$journal->no_bukti} berhasil dibuat.")
                                ->success()
                                ->send();
                        } catch (\RuntimeException $exception) {
                            Notification::make()
                                ->title('Gagal posting penyusutan')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->disabled(fn (AccountingPeriod $record) => $record->isClosed())
                    ->tooltip(fn (AccountingPeriod $record) => $record->isClosed() ? 'Periode yang sudah tutup buku tidak bisa diedit' : ''),

                DeleteAction::make()
                    ->disabled(fn (AccountingPeriod $record) => $record->isClosed())
                    ->tooltip(fn (AccountingPeriod $record) => $record->isClosed() ? 'Periode yang sudah tutup buku tidak bisa dihapus' : ''),
            ])
            ->bulkActions([
                //
            ]);
    }
}
