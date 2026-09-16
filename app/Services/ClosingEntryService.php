<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralJournal;
use App\Models\JournalItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClosingEntryService
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    public function post(AccountingPeriod $period): GeneralJournal
    {
        if ($period->isClosed()) {
            throw new RuntimeException('Periode sudah ditutup.');
        }

        if (GeneralJournal::where('tanggal', $period->end_date)
            ->where('source_type', 'CLOSING')
            ->exists()) {
            throw new RuntimeException('Jurnal penutup untuk periode ini sudah diposting.');
        }

        $accounts = ChartOfAccount::query()
            ->where(function ($query) {
                $query->where('kategori', 'like', 'Pendapatan%')
                    ->orWhere('kategori', 'like', 'Beban%');
            })
            ->orderBy('kode_akun')
            ->get();
        $modal = ChartOfAccount::where('kode_akun', '3101')->first();

        if (!$modal) {
            throw new RuntimeException('Akun Modal Pemilik (3101) belum tersedia.');
        }

        $lines = [];
        foreach ($accounts as $account) {
            $totals = JournalItem::where('coa_id', $account->id)
                ->whereHas('journal', function ($query) use ($period) {
                    $query->whereDate('tanggal', '>=', $period->start_date)
                        ->whereDate('tanggal', '<=', $period->end_date);
                })
                ->selectRaw('COALESCE(SUM(debit), 0) AS total_debit, COALESCE(SUM(kredit), 0) AS total_kredit')
                ->first();
            $debit = (int) $totals->total_debit;
            $credit = (int) $totals->total_kredit;
            $net = $debit - $credit;

            if ($net === 0) {
                continue;
            }

            if ($net > 0) {
                $lines[] = ['coa_id' => $account->id, 'debit' => 0, 'kredit' => $net];
            } else {
                $lines[] = ['coa_id' => $account->id, 'debit' => abs($net), 'kredit' => 0];
            }
        }

        $totalDebit = collect($lines)->sum('debit');
        $totalCredit = collect($lines)->sum('kredit');
        $modalAdjustment = $totalDebit - $totalCredit;

        if ($modalAdjustment > 0) {
            $lines[] = ['coa_id' => $modal->id, 'debit' => 0, 'kredit' => $modalAdjustment];
        } elseif ($modalAdjustment < 0) {
            $lines[] = ['coa_id' => $modal->id, 'debit' => abs($modalAdjustment), 'kredit' => 0];
        }

        if (count($lines) < 2) {
            throw new RuntimeException('Tidak ada saldo akun nominal yang perlu ditutup.');
        }

        return DB::transaction(fn () => $this->journalEntryService->createManualEntry(
            $period->end_date->toDateString(),
            'Jurnal Penutup ' . $period->label,
            $lines,
            null,
            'CLOSING',
        ));
    }
}
