<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Services\StockOpnameCsvService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateStockOpname extends CreateRecord
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Download Template CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->tooltip('Unduh template CSV yang telah otomatis terisi seluruh produk aktif terbaru')
                ->action(fn (StockOpnameCsvService $service) => $service->generateTemplateCsvStream()),
        ];
    }
}
