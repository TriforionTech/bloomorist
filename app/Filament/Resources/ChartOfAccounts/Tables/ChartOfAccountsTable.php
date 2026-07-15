<?php

namespace App\Filament\Resources\ChartOfAccounts\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChartOfAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('kode_akun', 'asc')
            ->columns([
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),

                TextColumn::make('kode_akun')
                    ->label('KODE AKUN')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->copyable(),

                TextColumn::make('nama_akun')
                    ->label('NAMA AKUN')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('kategori')
                    ->label('KATEGORI')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Aset'       => 'info',
                        'Kewajiban'  => 'danger',
                        'Ekuitas'    => 'success',
                        'Pendapatan' => 'warning',
                        'Beban'      => 'gray',
                        default      => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('saldo_normal')
                    ->label('SALDO NORMAL')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Debit'  => 'info',
                        'Kredit' => 'success',
                        default  => 'gray',
                    })
                    ->sortable()
                    ->extraAttributes([
                        'class' => 'text-lg font-semibold',
                    ]),
            ])
            ->recordActionsColumnLabel('ACTIONS')
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->size('xl')
                    ->icon('heroicon-o-pencil-square'),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->size('xl')
                    ->icon('heroicon-o-trash')
                    ->modalHeading('Hapus COA')
                    ->modalDescription('Apakah Anda yakin ingin menghapus akun ini? Tindakan ini tidak dapat dibatalkan.')
                    ->modalSubmitActionLabel('Ya, Hapus')
                    ->modalCancelActionLabel('Batal'),
            ]);
    }
}
