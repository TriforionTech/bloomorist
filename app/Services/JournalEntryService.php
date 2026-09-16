<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\GeneralJournal;
use App\Models\JournalItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JournalEntryService
{
    /**
     * Create a manual general journal entry with its items.
     * Validates that the sum of debit equals sum of kredit.
     *
     * @param string $tanggal
     * @param string $keterangan
     * @param array $items Array of ['coa_id' => int, 'debit' => int, 'kredit' => int]
     * @param string|null $noBukti
     * @return GeneralJournal
     */
    public function createManualEntry(
        string $tanggal,
        string $keterangan,
        array $items,
        ?string $noBukti = null,
        string $sourceType = 'MANUAL',
    ): GeneralJournal
    {
        $totalDebit = collect($items)->sum('debit');
        $totalKredit = collect($items)->sum('kredit');

        if ($totalDebit !== $totalKredit) {
            throw new InvalidArgumentException("Jurnal tidak seimbang. Debit: {$totalDebit}, Kredit: {$totalKredit}");
        }

        if ($totalDebit <= 0) {
            throw new InvalidArgumentException("Total jurnal harus lebih dari 0.");
        }

        return DB::transaction(function () use ($tanggal, $keterangan, $items, $noBukti, $sourceType) {
            $accountingService = app(AccountingService::class);

            $journal = GeneralJournal::create([
                'tanggal'      => $tanggal,
                'no_bukti'     => $noBukti ?? $accountingService->generateNoBukti('JU'),
                'keterangan'   => $keterangan,
                'reference_id' => null,
                'source_type'  => $sourceType,
            ]);

            foreach ($items as $item) {
                $coa = ChartOfAccount::findOrFail($item['coa_id']);
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id'     => $coa->id,
                    'kode_coa'   => $coa->kode_akun,
                    'debit'      => $item['debit'] ?? 0,
                    'kredit'     => $item['kredit'] ?? 0,
                ]);
            }

            app(AccountingAuditService::class)->record(
                'created',
                $journal,
                null,
                app(AccountingAuditService::class)->journalSnapshot($journal),
            );

            return $journal;
        });
    }
}
