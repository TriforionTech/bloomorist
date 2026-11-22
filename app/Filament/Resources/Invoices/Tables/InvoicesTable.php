<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Filament\Pages\GenerateInvoice;
use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class InvoicesTable
{
    /**
     * Transisi status yang diizinkan berdasarkan status saat ini.
     * Sesuai strict state machine di InvoiceStatusService.
     */
    private static function getAllowedTransitions(string $currentStatus): array
    {
        return match ($currentStatus) {
            'pending' => [
                'paid' => '✅ Lunas',
                'cancelled' => '❌ Dibatalkan',
            ],
            'paid' => [
                'cancelled' => '❌ Dibatalkan',
            ],
            'cancelled' => [], // Final — tidak ada transisi yang diperbolehkan
            default => [],
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['boxItem', 'wrappingItem', 'customer.membership'])
                ->withSum('regularItems', 'normal_price')
                ->withCount('regularItems')
                ->withSum('regularItems', 'quantity'))
            ->recordUrl(fn (Invoice $record) => GenerateInvoice::getUrl().'?invoice='.$record->id.($record->isEditable() ? '' : '&view=1'))
            ->columns([
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),
                TextColumn::make('invoice_number')
                    ->label('NO. INVOICE')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('customer_name')
                    ->label('PELANGGAN')
                    ->getStateUsing(fn (Invoice $record): string => $record->customer?->nama ?? $record->customer_name ?? '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('customer_name', 'like', "%{$search}%")
                            ->orWhereHas('customer', fn ($q) => $q->where('nama', 'like', "%{$search}%"));
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('customer_name', $direction);
                    })
                    ->description(function (Invoice $record): string {
                        if ($record->customer?->trashed()) {
                            return '(Customer Diarsipkan)';
                        }

                        return $record->customer_type === 'member'
                            ? 'Member '.($record->customer?->membership?->nama ?? '')
                            : 'Non-Member';
                    }),

                TextColumn::make('regular_items_count')
                    ->label('BARANG')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('regular_items_sum_quantity')
                    ->label('JUMLAH')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                TextColumn::make('regular_items_sum_normal_price')
                    ->label('SUBTOTAL')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state ?? 0, 0, ',', '.'))
                    ->sortable()
                    ->description('Sebelum diskon'),

                TextColumn::make('discount_total')
                    ->label('DISKON')
                    ->sortable()
                    ->color('danger')
                    ->formatStateUsing(fn ($state) => $state > 0 ? '-Rp '.number_format($state, 0, ',', '.') : 'Rp 0')
                    ->description(fn (Invoice $record): string => $record->customer_type === 'member'
                            ? ($record->discount_mode_member ? 'Kustom' : 'Membership')
                            : ($record->discount_mode === 'none' ? 'Tanpa diskon' : ucfirst($record->discount_mode ?? 'none'))
                    ),

                TextColumn::make('ongkir')
                    ->label('ONGKIR')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state ?? 0, 0, ',', '.'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('add_ons_total')
                    ->label('TAMBAHAN')
                    ->state(fn (Invoice $record): float => ($record->boxItem?->discount_price ?? 0) + ($record->wrappingItem?->discount_price ?? 0))
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state ?? 0, 0, ',', '.'))
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->description(fn (Invoice $record): string => collect([
                        $record->use_box ? 'Box x'.($record->boxItem?->quantity ?? 1) : null,
                        $record->use_wrapping ? 'Wrap x'.($record->wrappingItem?->quantity ?? 1) : null,
                    ])->filter()->implode(', ') ?: '-'
                    ),

                TextColumn::make('grand_total')
                    ->label('TOTAL AKHIR')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state ?? 0, 0, ',', '.'))
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('issued_date')
                    ->label('DITERBITKAN')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('JATUH TEMPO')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (Invoice $record): string => $record->status === 'pending' && $record->due_date?->isPast()
                            ? 'danger'
                            : 'gray'
                    ),

                TextColumn::make('status')
                    ->label('STATUS')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'cancelled' => 'danger',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '🕒 PENDING',
                        'paid' => '✅ LUNAS',
                        'cancelled' => '❌ DIBATALKAN',
                        default => strtoupper($state),
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => '🕒 Pending',
                        'paid' => '✅ Lunas',
                        'cancelled' => '❌ Dibatalkan',
                    ])
                    ->searchable(),
                SelectFilter::make('customer_type')
                    ->label('Tipe Pelanggan')
                    ->options([
                        'member' => 'Member',
                        'non_member' => 'Non-Member',
                    ])
                    ->searchable(),
                Filter::make('issued_date_range')
                    ->label('Tanggal Diterbitkan')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])->schema([
                            DatePicker::make('issued_from')
                                ->label('Dari Tanggal')
                                ->native(false)
                                ->displayFormat('d M Y'),
                            DatePicker::make('issued_until')
                                ->label('Sampai Tanggal')
                                ->native(false)
                                ->displayFormat('d M Y'),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['issued_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('issued_date', '>=', $date)
                            )
                            ->when(
                                $data['issued_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('issued_date', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['issued_from'] ?? null) {
                            $indicators['issued_from'] = 'Terbit Dari: '.Carbon::parse($data['issued_from'])->format('d M Y');
                        }
                        if ($data['issued_until'] ?? null) {
                            $indicators['issued_until'] = 'Terbit Sampai: '.Carbon::parse($data['issued_until'])->format('d M Y');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersFormWidth('xl')
            ->defaultSort('created_at', 'desc')
            ->recordActionsColumnLabel('AKSI')
            ->recordActions([
                // ─── Change Status ───────────────────────────────────────
                Action::make('changeStatus')
                    ->hiddenLabel()
                    ->icon('heroicon-o-arrow-path')
                    ->color(fn (Invoice $record): string => $record->isCancelled() ? 'gray' : 'info')
                    ->size('xl')
                    ->tooltip(fn (Invoice $record): string => match (true) {
                        $record->isCancelled() => 'Status Cancelled bersifat final dan tidak dapat diubah',
                        $record->isPaid() => 'Ubah status (hanya bisa ke Cancelled)',
                        default => 'Ubah status invoice',
                    })
                    ->disabled(fn (Invoice $record): bool => $record->isCancelled())
                    ->modalHeading('Ubah Status Invoice')
                    ->modalDescription(fn (Invoice $record) => "Invoice #{$record->invoice_number} — Status saat ini: ".strtoupper($record->status))
                    ->modalSubmitActionLabel('Ya, Ubah Status')
                    ->modalCancelActionLabel('Batal')
                    ->schema([
                        Select::make('new_status')
                            ->label('Status Baru')
                            ->options(fn (Invoice $record) => self::getAllowedTransitions($record->status))
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (Invoice $record, array $data) {
                        try {
                            Invoice::handleStatusChange($record, $data['new_status']);

                            Notification::make()
                                ->title('Status berhasil diubah')
                                ->body("Invoice #{$record->invoice_number} → ".strtoupper($data['new_status']))
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Gagal mengubah status')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // ─── Download PDF ────────────────────────────────────────
                Action::make('downloadPdf')
                    ->hiddenLabel()
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->size('xl')
                    ->url(fn (Invoice $record) => route('invoice.download', $record))
                    ->tooltip('Download PDF'),

                // ─── Edit, View & Delete (Grouped) ─────────────────────────────
                ActionGroup::make([
                    Action::make('view')
                        ->label('Lihat')
                        ->hiddenLabel()
                        ->icon('heroicon-o-eye')
                        ->color('primary')
                        ->size('xl')
                        ->tooltip('Lihat detail invoice')
                        ->url(fn (Invoice $record) => GenerateInvoice::getUrl().'?invoice='.$record->id.'&view=1'),

                    Action::make('edit')
                        ->hiddenLabel()
                        ->icon('heroicon-o-pencil-square')
                        ->color(fn (Invoice $record): string => $record->isEditable() ? 'warning' : 'gray')
                        ->size('xl')
                        ->tooltip(fn (Invoice $record): string => $record->isEditable()
                            ? 'Edit invoice'
                            : 'Invoice '.strtoupper($record->status).' tidak dapat diedit')
                        ->disabled(fn (Invoice $record): bool => ! $record->isEditable())
                        ->url(fn (Invoice $record) => $record->isEditable()
                            ? GenerateInvoice::getUrl().'?invoice='.$record->id
                            : null),

                    Action::make('delete')
                        ->label('Hapus')
                        ->hiddenLabel()
                        ->icon('heroicon-o-trash')
                        ->color(fn (Invoice $record): string => $record->isDeletable() ? 'danger' : 'gray')
                        ->size('xl')
                        ->tooltip(fn (Invoice $record): string => $record->isDeletable()
                            ? 'Hapus invoice'
                            : 'Invoice '.strtoupper($record->status).' tidak dapat dihapus')
                        ->disabled(fn (Invoice $record): bool => ! $record->isDeletable())
                        ->requiresConfirmation()
                        ->modalHeading('Hapus Invoice')
                        ->modalDescription('Apakah Anda yakin ingin menghapus invoice ini? Tindakan ini tidak dapat dibatalkan.')
                        ->modalSubmitActionLabel('Ya, Hapus')
                        ->modalCancelActionLabel('Batal')
                        ->action(function (Invoice $record) {
                            try {
                                $record->delete();

                                Notification::make()
                                    ->title('Invoice berhasil dihapus')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Gagal menghapus invoice')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('gray')
                    ->size(Size::Small)
                    ->tooltip('Aksi Lainnya'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // ─── Bulk Change Status ──────────────────────────────
                    BulkAction::make('changeStatus')
                        ->label('Ubah Status')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->modalHeading('Ubah Status Invoice Terpilih')
                        ->modalDescription('Pilih status baru. Hanya transisi yang valid yang akan diproses, invoice lainnya akan diskip.')
                        ->modalSubmitActionLabel('Ya, Ubah Status')
                        ->modalCancelActionLabel('Batal')
                        ->schema([
                            Select::make('new_status')
                                ->label('Status Baru')
                                ->options([
                                    'paid' => '✅ Lunas',
                                    'cancelled' => '❌ Dibatalkan',
                                ])
                                ->required()
                                ->native(false),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $updated = 0;
                            $skipped = 0;
                            $failed = 0;
                            $errorMessages = [];

                            foreach ($records as $record) {
                                // Skip jika status sama
                                if ($record->status === $data['new_status']) {
                                    $skipped++;

                                    continue;
                                }

                                // Cek apakah transisi diizinkan
                                $allowed = self::getAllowedTransitions($record->status);
                                if (! array_key_exists($data['new_status'], $allowed)) {
                                    $skipped++;

                                    continue;
                                }

                                try {
                                    Invoice::handleStatusChange($record, $data['new_status']);
                                    $updated++;
                                } catch (\Exception $e) {
                                    $failed++;
                                    $errorMessages[] = "Invoice #{$record->invoice_number}: ".$e->getMessage();
                                }
                            }

                            if ($updated > 0) {
                                Notification::make()
                                    ->title("{$updated} invoice berhasil diupdate")
                                    ->body("{$updated} invoice telah diubah statusnya menjadi ".strtoupper($data['new_status']).'.')
                                    ->success()
                                    ->send();
                            }

                            if ($skipped > 0) {
                                Notification::make()
                                    ->title("{$skipped} invoice diskip")
                                    ->body('Beberapa invoice diskip karena transisi status tidak valid.')
                                    ->warning()
                                    ->send();
                            }

                            if ($failed > 0) {
                                Notification::make()
                                    ->title("{$failed} invoice gagal diupdate")
                                    ->body(implode('<br>', $errorMessages))
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    // ─── Bulk Delete (hanya pending) ─────────────────────
                    BulkAction::make('bulkDelete')
                        ->label('Hapus Terpilih')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Hapus Invoice Terpilih')
                        ->modalDescription('Hanya invoice dengan status Pending yang akan dihapus. Invoice Paid dan Cancelled akan diskip.')
                        ->modalSubmitActionLabel('Ya, Hapus')
                        ->modalCancelActionLabel('Batal')
                        ->action(function (Collection $records) {
                            $deleted = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if ($record->isDeletable()) {
                                    try {
                                        $record->delete();
                                        $deleted++;
                                    } catch (\Exception $e) {
                                        $skipped++;
                                    }
                                } else {
                                    $skipped++;
                                }
                            }

                            if ($deleted > 0) {
                                Notification::make()
                                    ->title("{$deleted} invoice berhasil dihapus")
                                    ->success()
                                    ->send();
                            }

                            if ($skipped > 0) {
                                Notification::make()
                                    ->title("{$skipped} invoice diskip")
                                    ->body('Invoice Paid dan Cancelled tidak dapat dihapus.')
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
