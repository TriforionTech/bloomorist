<?php

namespace App\Filament\Resources\GeneralJournals\Pages;

use App\Filament\Resources\GeneralJournals\GeneralJournalResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditGeneralJournal extends EditRecord
{
    protected static string $resource = GeneralJournalResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index'); 
    }
    
    /**
     * Validate that total debit equals total kredit before saving.
     */
    protected function beforeSave(): void
    {
        $items = $this->data['items'] ?? [];

        $totalDebit  = collect($items)->sum(fn ($item) => (int) ($item['debit'] ?? 0));
        $totalKredit = collect($items)->sum(fn ($item) => (int) ($item['kredit'] ?? 0));

        if ($totalDebit !== $totalKredit) {
            Notification::make()
                ->danger()
                ->title('Jurnal Tidak Balance')
                ->body("Total Debit (Rp " . number_format($totalDebit, 0, ',', '.') . ") tidak sama dengan Total Kredit (Rp " . number_format($totalKredit, 0, ',', '.') . "). Jurnal harus balance.")
                ->persistent()
                ->send();

            $this->halt();
        }

        if ($totalDebit === 0) {
            Notification::make()
                ->danger()
                ->title('Jurnal Kosong')
                ->body('Total Debit dan Kredit tidak boleh nol.')
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
