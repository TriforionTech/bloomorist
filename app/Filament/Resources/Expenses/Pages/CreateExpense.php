<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Services\AccountingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index'); 
    }

    /**
     * After expense is created, auto-generate the journal entry.
     */
    protected function afterCreate(): void
    {
        try {
            app(AccountingService::class)->createExpenseJournal($this->record);

            Notification::make()
                ->success()
                ->title('Jurnal Otomatis Dibuat')
                ->body('Jurnal pengeluaran telah dibuat secara otomatis.')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Membuat Jurnal')
                ->body('Terjadi kesalahan saat membuat jurnal otomatis: ' . $e->getMessage())
                ->persistent()
                ->send();
        }
    }
}
