<?php

namespace App\Services;

use App\Models\AccountingAudit;
use App\Models\GeneralJournal;
use Illuminate\Support\Facades\Auth;

class AccountingAuditService
{
    public function record(
        string $action,
        ?GeneralJournal $journal = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AccountingAudit {
        return AccountingAudit::create([
            'user_id' => Auth::id(),
            'journal_id' => $journal?->id,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public function journalSnapshot(GeneralJournal $journal): array
    {
        $journal->loadMissing('items');

        return [
            'header' => $journal->only(['tanggal', 'no_bukti', 'keterangan', 'reference_id', 'source_type']),
            'items' => $journal->items->map(fn ($item) => [
                'coa_id' => $item->coa_id,
                'kode_coa' => $item->kode_coa,
                'debit' => $item->debit,
                'kredit' => $item->kredit,
            ])->values()->all(),
        ];
    }
}
