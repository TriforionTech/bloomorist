<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Filament\Pages\GenerateInvoice;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['boxItem', 'wrappingItem', 'customer.membership'])
                ->withSum('regularItems', 'normal_price')
                ->withCount('regularItems'))
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('INVOICE NO.')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

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
                    ->money('IDR')
                    ->sortable()
                    ->description('Sebelum diskon'),

                TextColumn::make('discount_total')
                    ->label('DISKON')
                    ->money('IDR')
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
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('add_ons_total')
                    ->label('ADD ONS')
                    ->state(fn (Invoice $record): float => ($record->boxItem?->discount_price ?? 0) + ($record->wrappingItem?->discount_price ?? 0))
                    ->money('IDR')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->description(fn (Invoice $record): string => 
                        collect([
                            $record->use_box ? "Box x" . ($record->boxItem?->quantity ?? 1) : null,
                            $record->use_wrapping ? "Wrap x" . ($record->wrappingItem?->quantity ?? 1) : null,
                        ])->filter()->implode(', ') ?: '-'
                    ),

                TextColumn::make('grand_total')
                    ->label('GRAND TOTAL')
                    ->money('IDR')
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
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'paid'      => 'success',
                        'cancelled' => 'danger',
                        'refunded'  => 'gray',
                        default     => 'primary',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'paid'      => 'Paid',
                        'cancelled' => 'Cancelled',
                        'refunded'  => 'Refunded',
                    ]),
                SelectFilter::make('customer_type')
                    ->label('Customer Type')
                    ->options([
                        'member'     => 'Member',
                        'non_member' => 'Non-Member',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActionsColumnLabel('ACTIONS')
            ->recordActions([
                Action::make('markAsPaid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record) => $record->status === 'pending')
                    ->action(fn (Invoice $record) => $record->update(['status' => 'paid']))
                    ->tooltip('Update status to paid'),

                Action::make('downloadPdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->url(fn (Invoice $record) => route('invoice.download', $record))
                    ->openUrlInNewTab()
                    ->tooltip('Download this invoice'),

                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->url(fn (Invoice $record) => GenerateInvoice::getUrl() . '?invoice=' . $record->id)
                    ->tooltip('Edit this invoice'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markAsPaid')
                        ->label('Mark as Paid')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Mark Selected Invoices as Paid')
                        ->modalDescription('Invoice yang statusnya sudah paid akan diabaikan. Hanya invoice dengan status pending yang akan diupdate.')
                        ->action(function (Collection $records) {
                            $updated = $records
                                ->where('status', 'pending')
                                ->each(fn (Invoice $record) => $record->update(['status' => 'paid']));
                            
                            $count = $updated->count();

                            \Filament\Notifications\Notification::make()
                                ->title("{$count} invoice berhasil diupdate")
                                ->body("{$count} invoice telah ditandai sebagai paid.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
