<?php

namespace App\Filament\Resources\AccountingPeriods\Tables;

use App\Models\AccountingPeriod;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

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
                    ->formatStateUsing(fn ($state) => $state !== null ? 'Rp ' . number_format($state, 0, ',', '.') : 'Belum Diisi')
                    ->color(fn ($state) => $state !== null ? 'success' : 'danger'),

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
            ->recordActionsColumnLabel('ACTIONS')
            ->recordActions([
                Action::make('tutupBuku')
                    ->label('Tutup Buku')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Tutup Periode Akuntansi')
                    ->modalDescription(fn (AccountingPeriod $record) => "Apakah Anda yakin ingin mengunci periode {$record->label}? Anda TIDAK AKAN BISA menambah atau mengubah transaksi (Invoices, Expenses) di periode ini lagi. Pastikan semua data sudah final.")
                    ->disabled(fn (AccountingPeriod $record) => $record->isClosed())
                    ->action(function (AccountingPeriod $record) {
                        // Validasi: closing_inventory_value harus sudah diisi
                        if ($record->closing_inventory_value === null) {
                            Notification::make()
                                ->title('Gagal Tutup Buku')
                                ->body('Anda harus mengisi nilai "Stock Opname Akhir" (closing inventory) melalui form Edit terlebih dahulu sebelum bisa menutup buku.')
                                ->danger()
                                ->send();
                            
                            return;
                        }

                        $record->update([
                            'status' => 'closed',
                            'closed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Periode Berhasil Ditutup')
                            ->body("Periode {$record->label} telah resmi dikunci.")
                            ->success()
                            ->send();
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
