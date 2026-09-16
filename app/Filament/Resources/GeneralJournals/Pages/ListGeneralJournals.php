<?php

namespace App\Filament\Resources\GeneralJournals\Pages;

use App\Filament\Resources\GeneralJournals\GeneralJournalResource;
use Filament\Resources\Pages\ListRecords;

class ListGeneralJournals extends ListRecords
{
    protected static string $resource = GeneralJournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
