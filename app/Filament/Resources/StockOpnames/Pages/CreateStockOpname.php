<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Services\StockOpnameExcelService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateStockOpname extends CreateRecord
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Download Template Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->tooltip('Unduh template Excel (.xlsx) resmi yang telah otomatis terisi seluruh produk aktif terbaru')
                ->action(fn (StockOpnameExcelService $service) => $service->generateTemplateExcel()),
        ];
    }
}
