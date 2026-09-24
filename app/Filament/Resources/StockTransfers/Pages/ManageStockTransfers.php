<?php

namespace App\Filament\Resources\StockTransfers\Pages;

use App\Filament\Resources\StockTransfers\StockTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStockTransfers extends ManageRecords
{
    protected static string $resource = StockTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->before(function (CreateAction $action, array $data) {
                    $tanggal = $data['tanggal'] ?? now()->toDateString();
                    $period = \App\Models\AccountingPeriod::where('start_date', '<=', $tanggal)
                        ->where('end_date', '>=', $tanggal)
                        ->first();

                    if ($period && $period->isClosed()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Periode Akuntansi Sudah Ditutup')
                            ->body("Periode '{$period->label}' saat ini berstatus Ditutup (Tutup Buku). Transfer stok tidak dapat disimpan.")
                            ->danger()
                            ->persistent()
                            ->send();

                        $action->halt();
                    }
                })
                ->after(function (\App\Models\StockTransfer $record) {
                    try {
                        $accountingService = new \App\Services\AccountingService;
                        $accountingService->createStockTransferJournal($record);
                    } catch (\App\Exceptions\AccountingPeriodClosedException $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Periode Akuntansi Sudah Ditutup')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\StockTransfers\Widgets\StockTransferOverview::class,
        ];
    }
}
