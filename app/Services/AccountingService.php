<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\GeneralJournal;
use App\Models\JournalItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    /**
     * Generate an auto-incrementing journal number.
     * Format: {prefix}-{YYYY}-{sequence}
     * Example: JU-2026-0001
     */
    public function generateNoBukti(string $prefix = 'JU'): string
    {
        $year = now()->format('Y');
        $pattern = "{$prefix}-{$year}-";

        $lastJournal = GeneralJournal::where('no_bukti', 'like', "{$pattern}%")
            ->orderByDesc('no_bukti')
            ->first();

        if ($lastJournal) {
            $lastNumber = (int) substr($lastJournal->no_bukti, strlen($pattern));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $pattern . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create journal entries automatically from an Expense record.
     * Debit: COA Beban (expense->coa_id)
     * Kredit: COA Kas/Bank (expense->coa_kredit_id, user-selected)
     */
    public function createExpenseJournal(Expense $expense): GeneralJournal
    {
        return DB::transaction(function () use ($expense) {
            $coaBeban  = ChartOfAccount::findOrFail($expense->coa_id);
            $coaKredit = ChartOfAccount::findOrFail($expense->coa_kredit_id);

            $journal = GeneralJournal::create([
                'no_bukti'     => $this->generateNoBukti('EXP'),
                'keterangan'   => $expense->keterangan,
                'reference_id' => $expense->id,
                'source_type'  => 'EXPENSE',
            ]);

            // Debit: Akun Beban
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id'     => $coaBeban->id,
                'kode_coa'   => $coaBeban->kode_akun,
                'debit'      => $expense->nominal,
                'kredit'     => 0,
            ]);

            // Kredit: Akun Kas/Bank (user-selected)
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id'     => $coaKredit->id,
                'kode_coa'   => $coaKredit->kode_akun,
                'debit'      => 0,
                'kredit'     => $expense->nominal,
            ]);

            return $journal;
        });
    }

    /**
     * Calculate opening balance for a COA before a given date.
     * Respects saldo_normal: Debit-normal = sum(debit) - sum(kredit), vice versa.
     */
    public function getOpeningBalance(int $coaId, Carbon $beforeDate): int
    {
        $coa = ChartOfAccount::findOrFail($coaId);

        $totals = JournalItem::where('coa_id', $coaId)
            ->whereHas('journal', function ($q) use ($beforeDate) {
                $q->where('created_at', '<', $beforeDate->startOfDay());
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(kredit), 0) as total_kredit')
            ->first();

        if ($coa->isDebitNormal()) {
            return $totals->total_debit - $totals->total_kredit;
        }

        return $totals->total_kredit - $totals->total_debit;
    }

    /**
     * Get ledger entries for a COA within a date range, with running balance.
     * Returns a collection of rows including a virtual "Saldo Awal" row at the top.
     */
    public function getLedgerEntries(int $coaId, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $coa = ChartOfAccount::findOrFail($coaId);

        // Calculate opening balance if start date is provided
        $openingBalance = 0;
        if ($startDate) {
            $openingBalance = $this->getOpeningBalance($coaId, $startDate);
        }

        // Build query for journal items
        $query = JournalItem::with('journal')
            ->where('coa_id', $coaId)
            ->whereHas('journal', function ($q) use ($startDate, $endDate) {
                if ($startDate) {
                    $q->where('created_at', '>=', $startDate->startOfDay());
                }
                if ($endDate) {
                    $q->where('created_at', '<=', $endDate->endOfDay());
                }
            })
            ->join('bl_general_journals_t', 'bl_journal_items_t.journal_id', '=', 'bl_general_journals_t.id')
            ->orderBy('bl_general_journals_t.created_at', 'asc')
            ->orderBy('bl_general_journals_t.id', 'asc')
            ->select('bl_journal_items_t.*')
            ->get();

        // Build rows with running balance
        $rows = collect();
        $runningBalance = $openingBalance;

        // Add opening balance row
        $rows->push([
            'tanggal'    => $startDate?->format('d/m/Y') ?? '-',
            'no_bukti'   => '-',
            'kode_coa'   => $coa->kode_akun,
            'keterangan' => 'Saldo Awal',
            'debit'      => 0,
            'kredit'     => 0,
            'saldo'      => $runningBalance,
            'is_opening' => true,
        ]);

        foreach ($query as $item) {
            if ($coa->isDebitNormal()) {
                $runningBalance += ($item->debit - $item->kredit);
            } else {
                $runningBalance += ($item->kredit - $item->debit);
            }

            $rows->push([
                'tanggal'    => $item->journal->created_at->format('d/m/Y'),
                'no_bukti'   => $item->journal->no_bukti,
                'kode_coa'   => $item->kode_coa,
                'keterangan' => $item->journal->keterangan,
                'debit'      => $item->debit,
                'kredit'     => $item->kredit,
                'saldo'      => $runningBalance,
                'is_opening' => false,
            ]);
        }

        return $rows;
    }

    /**
     * Get Income Statement (Laba Rugi) for a date range.
     * Returns grouped data by account with totals.
     */
    public function getIncomeStatement(Carbon $start, Carbon $end): array
    {
        // Pendapatan accounts
        $pendapatan = $this->getAccountGroupTotals('Pendapatan', $start, $end);
        $totalPendapatan = $pendapatan->sum('saldo');

        // Beban accounts
        $beban = $this->getAccountGroupTotals('Beban', $start, $end);
        $totalBeban = $beban->sum('saldo');

        // Net income
        $labaRugi = $totalPendapatan - $totalBeban;

        return [
            'pendapatan'       => $pendapatan,
            'total_pendapatan' => $totalPendapatan,
            'beban'            => $beban,
            'total_beban'      => $totalBeban,
            'laba_rugi'        => $labaRugi,
            'start_date'       => $start,
            'end_date'         => $end,
        ];
    }

    /**
     * Get Balance Sheet (Neraca) as of a specific date.
     * Includes Retained Earnings (Laba Ditahan) in Ekuitas.
     */
    public function getBalanceSheet(Carbon $asOf): array
    {
        $endOfDay = $asOf->copy()->endOfDay();

        // Aset
        $aset = $this->getAccountGroupTotals('Aset', null, $endOfDay);
        $totalAset = $aset->sum('saldo');

        // Kewajiban
        $kewajiban = $this->getAccountGroupTotals('Kewajiban', null, $endOfDay);
        $totalKewajiban = $kewajiban->sum('saldo');

        // Ekuitas
        $ekuitas = $this->getAccountGroupTotals('Ekuitas', null, $endOfDay);
        $totalEkuitasMurni = $ekuitas->sum('saldo');

        // Laba Ditahan (Retained Earnings) = net income from inception to asOf
        $labaDitahan = $this->getRetainedEarnings($endOfDay);

        $totalEkuitas = $totalEkuitasMurni + $labaDitahan;
        $totalKewajibanEkuitas = $totalKewajiban + $totalEkuitas;

        return [
            'aset'                    => $aset,
            'total_aset'              => $totalAset,
            'kewajiban'               => $kewajiban,
            'total_kewajiban'         => $totalKewajiban,
            'ekuitas'                 => $ekuitas,
            'total_ekuitas_murni'     => $totalEkuitasMurni,
            'laba_ditahan'            => $labaDitahan,
            'total_ekuitas'           => $totalEkuitas,
            'total_kewajiban_ekuitas' => $totalKewajibanEkuitas,
            'is_balanced'             => $totalAset === $totalKewajibanEkuitas,
            'as_of'                   => $asOf,
        ];
    }

    /**
     * Calculate Retained Earnings (Laba Ditahan) from system inception to a date.
     * = Total Pendapatan - Total Beban (all time up to date)
     */
    public function getRetainedEarnings(Carbon $asOf): int
    {
        $pendapatan = $this->getAccountGroupTotals('Pendapatan', null, $asOf);
        $beban      = $this->getAccountGroupTotals('Beban', null, $asOf);

        return $pendapatan->sum('saldo') - $beban->sum('saldo');
    }

    /**
     * Get totals for all accounts in a category within a date range.
     * Each account's saldo is computed respecting its saldo_normal direction.
     */
    private function getAccountGroupTotals(string $kategori, ?Carbon $start, ?Carbon $end): Collection
    {
        $accounts = ChartOfAccount::where('kategori', $kategori)->orderBy('kode_akun')->get();

        return $accounts->map(function ($account) use ($start, $end) {
            $query = JournalItem::where('coa_id', $account->id)
                ->whereHas('journal', function ($q) use ($start, $end) {
                    if ($start) {
                        $q->where('created_at', '>=', $start->startOfDay());
                    }
                    if ($end) {
                        $q->where('created_at', '<=', $end);
                    }
                });

            $totals = $query->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(kredit), 0) as total_kredit')
                ->first();

            $saldo = $account->isDebitNormal()
                ? $totals->total_debit - $totals->total_kredit
                : $totals->total_kredit - $totals->total_debit;

            return [
                'kode_akun' => $account->kode_akun,
                'nama_akun' => $account->nama_akun,
                'saldo'     => $saldo,
            ];
        });
    }
}
