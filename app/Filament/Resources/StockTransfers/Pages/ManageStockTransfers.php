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
                ->after(function (\App\Models\StockTransfer $record) {
                    $accountingService = new \App\Services\AccountingService;
                    $accountingService->createStockTransferJournal($record);
                }),
        ];
    }
}
