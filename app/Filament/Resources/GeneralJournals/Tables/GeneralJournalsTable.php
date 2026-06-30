<?php

namespace App\Filament\Resources\GeneralJournals\Tables;

use App\Filament\Resources\GeneralJournals\GeneralJournalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GeneralJournalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),

                TextColumn::make('no_bukti')
                    ->label('NO. BUKTI')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->copyable(),

                TextColumn::make('keterangan')
                    ->label('KETERANGAN')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('source_type')
                    ->label('SUMBER')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ?? 'MANUAL')
                    ->color(fn ($state) => match ($state) {
                        'EXPENSE' => 'danger',
                        'INVOICE' => 'success',
                        default   => 'info',
                    })
                    ->sortable(),

                TextColumn::make('items_sum_debit')
                    ->label('TOTAL DEBIT')
                    ->sum('items', 'debit')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->alignEnd(),

                TextColumn::make('items_sum_kredit')
                    ->label('TOTAL KREDIT')
                    ->sum('items', 'kredit')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state ?? 0, 0, ',', '.'))
                    ->alignEnd(),

                TextColumn::make('created_at')
                    ->label('TANGGAL')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->visible(fn ($record) => $record->reference_id === null),

                DeleteAction::make()
                    ->hiddenLabel()
                    ->visible(fn ($record) => $record->reference_id === null),
            ])
            ->recordUrl(function ($record) {
                // Only allow clicking into manual journals
                if ($record->reference_id !== null) {
                    return null;
                }
                return GeneralJournalResource::getUrl('edit', ['record' => $record]);
            });
    }
}
