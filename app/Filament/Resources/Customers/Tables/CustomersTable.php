<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nama', 'asc')
            ->columns([
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),
                TextColumn::make('nama')
                    ->label('NAMA')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipe_customer')
                    ->label('TIPE')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'toko' => 'success',
                        'vendor' => 'warning',
                        'dekor' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('alias')
                    ->label('ALIAS')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('EMAIL')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('alamat')
                    ->label('ALAMAT')
                    ->searchable(),
                TextColumn::make('kota')
                    ->label('KOTA')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provinsi')
                    ->label('PROVINSI')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('negara')
                    ->label('NEGARA')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nomor_hp')
                    ->label('NO. HP')
                    ->searchable(),
                TextColumn::make('membership_id')
                    ->label('MEMBERSHIP')
                    // ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => 'SILVER',
                        2 => 'GOLD',
                        3 => 'PLATINUM',
                        default => '-',
                    })
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        1 => 'gray',
                        2 => 'warning',
                        3 => 'info',
                        default => 'secondary',
                    })
                    ->icon(fn (int $state): string => match ($state) {
                        1 => 'heroicon-m-shield-check',
                        2 => 'heroicon-m-star',
                        3 => 'heroicon-m-trophy',
                        default => 'heroicon-m-minus',
                    }),
                TextColumn::make('created_at')
                    ->label('DIBUAT PADA')
                    ->dateTime('d M Y H:i')
                    ->description(fn ($record) => $record->updated_at->diffForHumans())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('DIPERBARUI PADA')
                    ->dateTime('d M Y H:i')
                    ->description(fn ($record) => $record->updated_at->diffForHumans())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipe_customer')
                    ->label('Tipe Pelanggan')
                    ->options([
                        'toko' => 'Toko',
                        'vendor' => 'Vendor',
                        'dekor' => 'Dekorator',
                    ])
                    ->native(false),
                SelectFilter::make('membership_id')
                    ->label('Kategori Member')
                    ->options([
                        1 => 'Silver',
                        2 => 'Gold',
                        3 => 'Platinum',
                    ])
                    ->native(false),

                TrashedFilter::make()
                    ->label('Status Arsip'),
            ])
            ->recordActionsColumnLabel('AKSI')
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->size('xl')
                    ->tooltip('Edit pelanggan')
                    ->icon('heroicon-o-pencil-square'),

                DeleteAction::make()
                    ->hiddenLabel()
                    ->size('xl')
                    ->tooltip('Hapus / Arsipkan pelanggan')
                    ->icon('heroicon-o-trash')
                    ->modalHeading('Hapus / Arsipkan Pelanggan')
                    ->modalDescription(function (Customer $record): string {
                        $invoiceCount = $record->invoices()->count();
                        if ($invoiceCount > 0) {
                            return 'Pelanggan ini memiliki '.$invoiceCount.' transaksi invoice, pelanggan akan diarsipkan. Riwayat transaksi dan laporan keuangan tidak akan hilang.';
                        }

                        return 'Apakah Anda yakin ingin menghapus pelanggan ini? Pelanggan akan dipindahkan ke arsip.';
                    })
                    ->modalSubmitActionLabel('Ya, Hapus')
                    ->modalCancelActionLabel('Batal'),

                RestoreAction::make()
                    ->hiddenLabel()
                    ->size('xl')
                    ->tooltip('Pulihkan pelanggan')
                    ->icon('heroicon-o-arrow-path'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Hapus / Arsipkan Pelanggan Terpilih')
                        ->modalDescription('Apakah Anda yakin ingin menghapus pelanggan yang dipilih? Riwayat transaksi dan laporan keuangan tidak akan hilang.')
                        ->modalSubmitActionLabel('Ya, Hapus Semua')
                        ->modalCancelActionLabel('Batal')
                        ->visible(fn () => Filament::auth()->user()?->is_super_admin),

                    RestoreBulkAction::make()
                        ->modalHeading('Pulihkan Pelanggan Terpilih')
                        ->modalSubmitActionLabel('Ya, Pulihkan Semua')
                        ->modalCancelActionLabel('Batal')
                        ->visible(fn () => Filament::auth()->user()?->is_super_admin),
                ]),
            ]);
    }
}
