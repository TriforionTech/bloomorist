<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Models\Expense;
use App\Services\ExpenseService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ExpensesTable
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
                    ->label('TANGGAL')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->label('KETERANGAN')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('nominal')
                    ->label('NOMINAL')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('danger'),

                TextColumn::make('coaBeban.nama_akun')
                    ->label('AKUN BEBAN')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('coaKredit.nama_akun')
                    ->label('AKUN KAS/BANK')
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->label('STATUS')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'draft'  => 'warning',
                        'posted' => 'success',
                        'void'   => 'danger',
                        default  => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft'  => '📝 DRAFT',
                        'posted' => '✅ POSTED',
                        'void'   => '❌ VOID',
                        default  => strtoupper($state),
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft'  => '📝 Draft',
                        'posted' => '✅ Posted',
                        'void'   => '❌ Void',
                    ])
                    ->searchable(),
            ])
            ->recordActionsColumnLabel('ACTIONS')
            ->recordActions([
                // ─── Post Expense ────────────────────────────────────────
                Action::make('postExpense')
                    ->hiddenLabel()
                    ->icon('heroicon-o-check-circle')
                    ->color(fn (Expense $record): string => $record->isDraft() ? 'success' : 'gray')
                    ->size('xl')
                    ->tooltip(fn (Expense $record): string => $record->isDraft()
                        ? 'Post expense (buat jurnal)'
                        : 'Expense sudah ' . strtoupper($record->status))
                    ->disabled(fn (Expense $record): bool => ! $record->isDraft())
                    ->requiresConfirmation()
                    ->modalHeading('Post Expense')
                    ->modalDescription(fn (Expense $record) =>
                        "Expense \"{$record->keterangan}\" (Rp " . number_format($record->nominal, 0, ',', '.') . ") akan diposting dan jurnal umum akan dibuat. Setelah diposting, expense tidak dapat diedit atau dihapus."
                    )
                    ->modalSubmitActionLabel('Ya, Post Expense')
                    ->modalCancelActionLabel('Batal')
                    ->action(function (Expense $record) {
                        try {
                            app(ExpenseService::class)->postExpense($record);

                            Notification::make()
                                ->title('Expense berhasil diposting')
                                ->body("Expense \"{$record->keterangan}\" telah diposting dan jurnal umum telah dibuat.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Gagal memposting expense')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // ─── Void Expense ────────────────────────────────────────
                Action::make('voidExpense')
                    ->hiddenLabel()
                    ->icon('heroicon-o-x-circle')
                    ->color(fn (Expense $record): string => $record->isPosted() ? 'danger' : 'gray')
                    ->size('xl')
                    ->tooltip(fn (Expense $record): string => match (true) {
                        $record->isPosted() => 'Void expense (buat reversing journal)',
                        $record->isVoid()   => 'Expense sudah VOID (final)',
                        default             => 'Post terlebih dahulu sebelum void',
                    })
                    ->disabled(fn (Expense $record): bool => ! $record->isPosted())
                    ->requiresConfirmation()
                    ->modalHeading('Void Expense')
                    ->modalDescription(fn (Expense $record) =>
                        "Expense \"{$record->keterangan}\" (Rp " . number_format($record->nominal, 0, ',', '.') . ") akan di-void. Reversing journal akan dibuat untuk membatalkan jurnal asli. Jurnal asli TIDAK akan dihapus."
                    )
                    ->modalSubmitActionLabel('Ya, Void Expense')
                    ->modalCancelActionLabel('Batal')
                    ->action(function (Expense $record) {
                        try {
                            app(ExpenseService::class)->voidExpense($record);

                            Notification::make()
                                ->title('Expense berhasil di-void')
                                ->body("Expense \"{$record->keterangan}\" telah di-void dan reversing journal telah dibuat.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Gagal mem-void expense')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // ─── Edit ────────────────────────────────────────────────
                EditAction::make()
                    ->hiddenLabel()
                    ->size('xl')
                    ->icon('heroicon-o-pencil-square')
                    ->color(fn (Expense $record): string => $record->isEditable() ? 'warning' : 'gray')
                    ->tooltip(fn (Expense $record): string => $record->isEditable()
                        ? 'Edit expense'
                        : 'Expense ' . strtoupper($record->status) . ' tidak dapat diedit')
                    ->disabled(fn (Expense $record): bool => ! $record->isEditable()),

                // ─── Delete ──────────────────────────────────────────────
                DeleteAction::make()
                    ->hiddenLabel()
                    ->size('xl')
                    ->icon('heroicon-o-trash')
                    ->color(fn (Expense $record): string => $record->isDeletable() ? 'danger' : 'gray')
                    ->tooltip(fn (Expense $record): string => $record->isDeletable()
                        ? 'Hapus expense'
                        : 'Expense ' . strtoupper($record->status) . ' tidak dapat dihapus')
                    ->disabled(fn (Expense $record): bool => ! $record->isDeletable())
                    ->modalHeading('Hapus Expense')
                    ->modalDescription('Apakah Anda yakin ingin menghapus expense ini? Tindakan ini tidak dapat dibatalkan.')
                    ->modalSubmitActionLabel('Ya, Hapus')
                    ->modalCancelActionLabel('Batal'),
            ]);
    }
}
