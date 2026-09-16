<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\GeneralJournal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DepreciationService
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    public function calculate(FixedAsset $asset, Carbon $periodEnd): array
    {
        $monthsElapsed = $asset->purchase_date
            ->copy()
            ->startOfMonth()
            ->diffInMonths($periodEnd->copy()->startOfMonth()->addMonth());
        $monthsElapsed = max(0, min($monthsElapsed, $asset->useful_life_months));
        $monthly = round((float) $asset->acquisition_cost / $asset->useful_life_months, 2);
        $accumulated = round($monthly * $monthsElapsed, 2);

        return [
            'monthly' => $monthly,
            'accumulated' => $accumulated,
            'netBook' => round((float) $asset->acquisition_cost - $accumulated, 2),
        ];
    }

    public function postMonthlyDepreciation(AccountingPeriod $period): GeneralJournal
    {
        if ($period->isClosed()) {
            throw new RuntimeException('Periode sudah ditutup.');
        }

        if (GeneralJournal::where('tanggal', $period->end_date)
            ->where('source_type', 'DEPRECIATION')
            ->exists()) {
            throw new RuntimeException('Penyusutan untuk periode ini sudah diposting.');
        }

        $assets = FixedAsset::active()->get();
        if ($assets->isEmpty()) {
            throw new RuntimeException('Belum ada aset tetap aktif untuk disusutkan.');
        }
        $previousPeriod = AccountingPeriod::query()
            ->whereDate('end_date', '<', $period->start_date)
            ->orderByDesc('end_date')
            ->first();
        $previousAccumulated = $previousPeriod
            ? (float) FixedAssetDepreciation::where('accounting_period_id', $previousPeriod->id)->sum('accumulated_depreciation')
            : 0;
        $currentRows = [];
        $currentAccumulated = 0;

        foreach ($assets as $asset) {
            $calculation = $this->calculate($asset, $period->end_date);
            $currentAccumulated += $calculation['accumulated'];
            $currentRows[] = [$asset, $calculation];
        }

        $amountToPost = round($currentAccumulated - $previousAccumulated, 2);
        if ($amountToPost < 0) {
            throw new RuntimeException('Akumulasi penyusutan periode berjalan tidak boleh lebih kecil dari periode sebelumnya.');
        }

        return DB::transaction(function () use ($period, $currentRows, $amountToPost) {
            $journal = $this->journalEntryService->createManualEntry(
                $period->end_date->toDateString(),
                'Penyusutan Peralatan',
                [
                    ['coa_id' => $this->accountIdForRows($currentRows, 'expense_account_id'), 'debit' => $amountToPost, 'kredit' => 0],
                    ['coa_id' => $this->accountIdForRows($currentRows, 'accum_dep_account_id'), 'debit' => 0, 'kredit' => $amountToPost],
                ],
                null,
                'DEPRECIATION',
            );

            foreach ($currentRows as [$asset, $calculation]) {
                FixedAssetDepreciation::create([
                    'fixed_asset_id' => $asset->id,
                    'accounting_period_id' => $period->id,
                    'monthly_depreciation' => $calculation['monthly'],
                    'accumulated_depreciation' => $calculation['accumulated'],
                    'net_book_value' => $calculation['netBook'],
                    'journal_entry_id' => $journal->id,
                ]);
            }

            return $journal;
        });
    }

    private function accountIdForRows(array $rows, string $field): int
    {
        $accountIds = collect($rows)
            ->map(fn (array $row) => $row[0]->{$field})
            ->unique()
            ->values();

        if ($accountIds->count() !== 1) {
            throw new RuntimeException('Seluruh aset harus menggunakan akun penyusutan yang sama untuk posting gabungan.');
        }

        return (int) $accountIds->first();
    }
}
