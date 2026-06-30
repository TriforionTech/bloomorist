<?php

namespace App\Filament\Resources\Expenses\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
            ])
            ->recordActions([
                EditAction::make()->hiddenLabel(),
                DeleteAction::make()->hiddenLabel(),
            ]);
    }
}
