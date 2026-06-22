<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Filament\Pages\GenerateInvoice;
use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Enums\Size;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['boxItem', 'wrappingItem', 'customer.membership'])
                ->withSum('regularItems', 'normal_price')
                ->withCount('regularItems'))
            ->columns([
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),
                TextColumn::make('invoice_number')
                    ->label('INVOICE NO.')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('customer.nama')
                    ->label('CUSTOMER')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Invoice $record): string => 
                        $record->customer_type === 'member' 
                            ? 'Member ' . ($record->customer?->membership?->nama ?? '') 
                            : 'Non-Member'
                    ),

                TextColumn::make('regular_items_count')
                    ->label('ITEMS')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('regular_items_sum_normal_price')
                    ->label('SUBTOTAL')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->sortable()
                    ->description('Sebelum diskon'),

                TextColumn::make('discount_total')
                    ->label('DISKON')
                    ->sortable()
                    ->color('danger')
                    ->formatStateUsing(fn ($state) => $state > 0 ? '-Rp ' . number_format($state, 0, ',', '.') : 'Rp 0')
                    ->description(fn (Invoice $record): string => 
                        $record->customer_type === 'member' 
                            ? ($record->discount_mode_member ? 'Custom' : 'Membership') 
                            : ($record->discount_mode === 'none' ? 'Tanpa diskon' : ucfirst($record->discount_mode ?? 'none'))
                    ),

                TextColumn::make('ongkir')
                    ->label('ONGKIR')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('add_ons_total')
                    ->label('ADD ONS')
                    ->state(fn (Invoice $record): float => ($record->boxItem?->discount_price ?? 0) + ($record->wrappingItem?->discount_price ?? 0))
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->description(fn (Invoice $record): string => 
                        collect([
                            $record->use_box ? "Box x" . ($record->boxItem?->quantity ?? 1) : null,
                            $record->use_wrapping ? "Wrap x" . ($record->wrappingItem?->quantity ?? 1) : null,
                        ])->filter()->implode(', ') ?: '-'
                    ),

                TextColumn::make('grand_total')
                    ->label('GRAND TOTAL')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('issued_date')
                    ->label('ISSUED')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('DUE')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (Invoice $record): string => 
                        $record->status === 'pending' && $record->due_date?->isPast() 
                            ? 'danger' 
                            : 'gray'
                    ),

                TextColumn::make('status')
                    ->label('STATUS')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'paid'      => 'success',
                        'cancelled' => 'danger',
                        'refunded'  => 'info',
                        default     => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => '🕒 PENDING',
                        'paid'      => '✅ PAID',
                        'cancelled' => '❌ CANCELLED',
                        'refunded'  => '↩️ REFUNDED',
                        default     => strtoupper($state),
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'   => '🕒 Pending',
                        'paid'      => '✅ Paid',
                        'cancelled' => '❌ Cancelled',
                        'refunded'  => '↩️ Refunded',
                    ])
                    ->searchable(),
                SelectFilter::make('customer_type')
                    ->label('Customer Type')
                    ->options([
                        'member'     => 'Member',
                        'non_member' => 'Non-Member',
                    ])
                    ->searchable(),
                Filter::make('issued_date_range')
                    ->label('Issued Date')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])->schema([
                            DatePicker::make('issued_from')
                                ->label('Issued From')
                                ->native(false)
                                ->displayFormat('d M Y'),
                            DatePicker::make('issued_until')
                                ->label('Issued Until')
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
                            $indicators['issued_from'] = 'Issued From: ' . Carbon::parse($data['issued_from'])->format('d M Y');
                        }
                        if ($data['issued_until'] ?? null) {
                            $indicators['issued_until'] = 'Issued Until: ' . Carbon::parse($data['issued_until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
            ])
            ->filtersFormWidth('xl')
            ->defaultSort('created_at', 'desc')
            ->recordActionsColumnLabel('ACTIONS')
            ->recordActions([
                Action::make('changeStatus')
                    ->hiddenLabel()
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->size('xl')
                    ->tooltip('Update invoice status')
                    ->modalHeading('Ubah Status Invoice')
                    ->modalDescription(fn (Invoice $record) => "Invoice #{$record->invoice_number} — Status saat ini: " . strtoupper($record->status))
                    ->modalSubmitActionLabel('Ya, Ubah Status')
                    ->modalCancelActionLabel('Batal')
                    ->schema([
                        Select::make('new_status')
                            ->label('Status Baru')
                            ->options(fn (Invoice $record) => collect([
                                'pending'   => '🕒 Pending',
                                'paid'      => '✅ Paid',
                                'cancelled' => '❌ Cancelled',
                                'refunded'  => '↩️ Refunded',
                            ])->except([$record->status])->toArray())
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (Invoice $record, array $data) {
                        Invoice::handleStatusChange($record, $data['new_status']);

                        Notification::make()
                            ->title('Status berhasil diubah')
                            ->body("Invoice #{$record->invoice_number} → " . strtoupper($data['new_status']))
                            ->success()
                            ->send();
                    }),
                Action::make('downloadPdf')
                        ->hiddenLabel()
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->size('xl')
                        ->url(fn (Invoice $record) => route('invoice.download', $record))
                        // ->openUrlInNewTab()
                        ->tooltip('Download PDF'),
                ActionGroup::make([
                    Action::make('edit')
                        ->hiddenLabel()
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->size('xl')
                        ->url(fn (Invoice $record) => GenerateInvoice::getUrl() . '?invoice=' . $record->id),
                        // ->tooltip('Edit invoice'),
                    
                    Action::make('delete')
                    ->hiddenLabel()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->size('xl')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Invoice')
                    ->modalDescription('Apakah Anda yakin ingin menghapus invoice ini? Tindakan ini tidak dapat dibatalkan.')
                    ->modalSubmitActionLabel('Ya, Hapus')
                    ->modalCancelActionLabel('Batal')
                    ->action(fn (Invoice $record) => $record->delete()),
                    // ->tooltip('Delete invoice'), 
                ])
                ->icon('heroicon-m-ellipsis-horizontal')
                ->color('gray')
                ->size(Size::Small)
                ->tooltip('More Actions'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('changeStatus')
                        ->label('Update status')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->modalHeading('Ubah Status Invoice Terpilih')
                        ->modalDescription('Pilih status baru untuk semua invoice yang dicentang. Invoice yang sudah memiliki status yang sama akan diabaikan.')
                        ->modalSubmitActionLabel('Ya, Ubah Status')
                        ->modalCancelActionLabel('Batal')
                        ->schema([
                            Select::make('new_status')
                                ->label('Status Baru')
                                ->options([
                                    'pending'   => '🕒 Pending',
                                    'paid'      => '✅ Paid',
                                    'cancelled' => '❌ Cancelled',
                                    'refunded'  => '↩️ Refunded',
                                ])
                                ->required()
                                ->native(false),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $updated = 0;

                            foreach ($records as $record) {
                                if ($record->status !== $data['new_status']) {
                                    Invoice::handleStatusChange($record, $data['new_status']);
                                    $updated++;
                                }
                            }

                            Notification::make()
                                ->title("{$updated} invoice berhasil diupdate")
                                ->body("{$updated} invoice telah diubah statusnya menjadi " . strtoupper($data['new_status']) . ".")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make()
                        ->modalHeading('Hapus Invoice Terpilih')
                        ->modalDescription('Apakah Anda yakin ingin menghapus semua invoice yang dipilih? Tindakan ini tidak dapat dibatalkan.')
                        ->modalSubmitActionLabel('Ya, Hapus Semua')
                        ->modalCancelActionLabel('Batal'),
                ]),
            ]);
    }
}
