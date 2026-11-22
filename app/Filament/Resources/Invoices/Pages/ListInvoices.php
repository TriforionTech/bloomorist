<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Pages\GenerateInvoice;
use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newInvoice')
                ->label('Buat Invoice')
                ->icon(Heroicon::Plus)
                ->url(fn (): string => GenerateInvoice::getUrl()),
        ];
    }
}
