<?php

namespace App\Filament\Resources\AccountingPeriods\Pages;

use App\Filament\Resources\AccountingPeriods\AccountingPeriodResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountingPeriod extends CreateRecord
{
    protected static string $resource = AccountingPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('panduan')
                ->label('📖 Buku Panduan (Manual)')
                ->color('info')
                ->modalHeading('Panduan Penggunaan Periode Akuntansi')
                ->modalContent(view('filament.manuals.accounting-period-modal'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $month = $data['month'] ?? (! empty($data['start_date']) ? Carbon::parse($data['start_date'])->format('m') : now()->format('m'));
        $year = $data['year'] ?? (! empty($data['start_date']) ? Carbon::parse($data['start_date'])->format('Y') : now()->year);

        $date = Carbon::createFromDate((int) $year, (int) $month, 1);

        $monthNames = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
            '07' => 'Juli',    '08' => 'Agustus',  '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];
        $monthKey = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $monthLabel = $monthNames[$monthKey] ?? $date->translatedFormat('F');

        $data['start_date'] = $date->copy()->startOfMonth()->toDateString();
        $data['end_date'] = $date->copy()->endOfMonth()->toDateString();
        $data['label'] = "{$monthLabel} {$year}";

        unset($data['month'], $data['year']);

        return $data;
    }
}
