<?php

namespace App\Filament\Resources\StockOpnames\Tables;

use App\Models\AccountingPeriod;
use App\Services\AccountingService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockOpnamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'posted' => 'success',
                        default => 'primary',
                    }),
                TextColumn::make('notes')
                    ->limit(50),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'posted' => 'Posted',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn ($record) => $record->status !== 'posted'),
                Action::make('post')
                    ->label('Post')
                    ->color('success')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record) {
                        $date = Carbon::parse($record->date);
                        $period = AccountingPeriod::where('start_date', '<=', $date)
                            ->where('end_date', '>=', $date)
                            ->first();

                        if (! $period) {
                            Notification::make()
                                ->title('Periode Akuntansi Belum Dibuat')
                                ->body('Tidak ada periode akuntansi untuk tanggal '.$date->format('d/m/Y'))
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($period->isClosed()) {
                            Notification::make()
                                ->title('Periode Sudah Ditutup')
                                ->body('Periode akuntansi untuk tanggal '.$date->format('d/m/Y').' sudah berstatus closed.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            app(AccountingService::class)->createStockOpnameJournal($record);

                            Notification::make()
                                ->title('Posted Successfully')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Gagal Memposting Stock Opname')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                //
            ]);
    }
}
