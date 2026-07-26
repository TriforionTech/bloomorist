<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index'); 
    }

    /**
     * Expense baru dibuat dengan status 'draft'.
     * Jurnal TIDAK langsung dibuat — jurnal baru dibuat saat user
     * melakukan action "Post Expense" di tabel.
     *
     * Ini sesuai prinsip ERP: Draft → Posted (dengan jurnal) → Void (dengan reversing journal).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        return $data;
    }
}
