<?php

namespace App\Filament\Resources\AccountingPeriods\Pages;

use App\Filament\Resources\AccountingPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountingPeriod extends CreateRecord
{
    protected static string $resource = AccountingPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('panduan')
                ->label('📖 Buku Panduan (Manual)')
                ->color('info')
                ->modalHeading('Panduan Penggunaan Periode Akuntansi')
                ->modalContent(view('filament.manuals.accounting-period-modal'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),
        ];
    }
}
