<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalItem;
use Illuminate\Support\Collection;

class TrialBalanceService
{
    /**
     * Return one net debit/credit row for every account in the period.
     */
    public function getForPeriod(int $periodId): array
    {
        AccountingPeriod::findOrFail($periodId);

        $totals = JournalItem::query()
            ->join('bl_general_journals_t', 'bl_general_journals_t.id', '=', 'bl_journal_items_t.journal_id')
            ->join('bl_accounting_periods_t', function ($join) use ($periodId) {
                $join->where('bl_accounting_periods_t.id', $periodId)
                    ->whereColumn('bl_general_journals_t.tanggal', '>=', 'bl_accounting_periods_t.start_date')
                    ->whereColumn('bl_general_journals_t.tanggal', '<=', 'bl_accounting_periods_t.end_date');
            })
            ->groupBy('coa_id')
            ->selectRaw('coa_id, COALESCE(SUM(debit), 0) AS total_debit, COALESCE(SUM(kredit), 0) AS total_kredit')
            ->get()
            ->keyBy('coa_id');

        $rows = ChartOfAccount::query()
            ->orderBy('kode_akun')
            ->get()
            ->map(function (ChartOfAccount $account) use ($totals): array {
                $total = $totals->get($account->id);
                $debit = (int) ($total?->total_debit ?? 0);
                $credit = (int) ($total?->total_kredit ?? 0);
                $net = $debit - $credit;

                return [
                    'account' => $account,
                    'debit' => max(0, $net),
                    'credit' => max(0, -$net),
                    'total_debit' => $debit,
                    'total_credit' => $credit,
                ];
            });

        return [
            'rows' => $rows,
            'total_debit' => $rows->sum('debit'),
            'total_credit' => $rows->sum('credit'),
            'is_balanced' => $rows->sum('debit') === $rows->sum('credit'),
        ];
    }
}
