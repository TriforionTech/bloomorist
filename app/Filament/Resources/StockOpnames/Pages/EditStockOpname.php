<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Models\AccountingPeriod;
use App\Services\AccountingService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStockOpname extends EditRecord
{
    protected static string $resource = StockOpnameResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record->status === 'posted') {
            abort(403, 'Cannot edit a posted Stock Opname.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('post')
                ->label('Post to Accounting')
                ->color('success')
                ->requiresConfirmation()
                ->icon('heroicon-o-check-circle')
                ->visible(fn () => $this->record->status === 'draft')
                ->action(function () {
                    $record = $this->record;

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

                        $this->refreshFormData(['status']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Gagal Memposting Stock Opname')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
