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
            \Filament\Actions\Action::make('panduan')
                ->label('📖 Buku Panduan (Manual)')
                ->color('info')
                ->modalHeading('Panduan Penggunaan Jurnal Umum')
                ->modalContent(view('filament.manuals.general-journal-modal'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
