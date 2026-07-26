<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index'); 
    }

    /**
     * Server-side guard: hanya expense Draft yang boleh diedit.
     * Jika user mengakses URL edit langsung untuk expense Posted/Void,
     * redirect ke halaman list dengan pesan error.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! $this->record->isEditable()) {
            Notification::make()
                ->title('Expense tidak dapat diedit')
                ->body('Hanya expense dengan status Draft yang dapat diedit.')
                ->danger()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));
        }
    }

    /**
     * Server-side guard tambahan: validasi sebelum save.
     * Double-check agar expense yang sedang di-save masih berstatus draft
     * (bisa berubah karena race condition antara mount dan save).
     */
    protected function beforeSave(): void
    {
        // Refresh record dari database
        $this->record->refresh();

        if (! $this->record->isEditable()) {
            Notification::make()
                ->title('Expense tidak dapat disimpan')
                ->body('Status expense telah berubah menjadi ' . strtoupper($this->record->status) . '. Perubahan Anda dibatalkan.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
