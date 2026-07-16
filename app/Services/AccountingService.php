<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\GeneralJournal;
use App\Models\Invoice;
use App\Models\JournalItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    /**
     * Create journal entries for stock adjustments (Spoilage / Damages / Corrections).
     * Debit: 5060 Beban Kerusakan Barang (Spoilage/Shrinkage Expense) [for loss]
     * Kredit: 1030 Persediaan Barang (Inventory)
     * Note: If stock is added (in), the journal is reversed.
     */
    public function createStockAdjustmentJournal(\App\Models\Product $product, int $quantity, string $type, string $notes = ''): ?GeneralJournal
    {
        return DB::transaction(function () use ($product, $quantity, $type, $notes) {
            // Find or create COA for Persediaan Barang
            $coaPersediaan = ChartOfAccount::firstOrCreate(
                ['kode_akun' => '1030'],
                ['nama_akun' => 'Persediaan Barang', 'kategori' => 'Aset', 'saldo_normal' => 'Debit']
            );

            // Find or create COA for Beban Kerusakan
            $coaBeban = ChartOfAccount::firstOrCreate(
                ['kode_akun' => '5060'],
                ['nama_akun' => 'Beban Kerusakan Barang', 'kategori' => 'Beban', 'saldo_normal' => 'Debit']
            );

            $amount = (int) ($product->harga_beli * $quantity);

            if ($amount <= 0) return null;

            $journal = GeneralJournal::create([
                'no_bukti'     => $this->generateNoBukti('ADJ'),
                'keterangan'   => "Penyesuaian Stok ({$type}) - {$product->nama} - {$notes}",
                'reference_id' => $product->id,
                'source_type'  => 'STOCK_ADJUSTMENT',
            ]);

            if ($type === 'loss' || $type === 'out') {
                // Barang Hilang/Rusak: Debit Beban, Kredit Persediaan
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id'     => $coaBeban->id,
                    'kode_coa'   => $coaBeban->kode_akun,
                    'debit'      => $amount,
                    'kredit'     => 0,
                ]);

                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id'     => $coaPersediaan->id,
                    'kode_coa'   => $coaPersediaan->kode_akun,
                    'debit'      => 0,
                    'kredit'     => $amount,
                ]);
            } else {
                // Barang Masuk (Opname Plus): Debit Persediaan, Kredit Beban (koreksi beban)
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id'     => $coaPersediaan->id,
                    'kode_coa'   => $coaPersediaan->kode_akun,
                    'debit'      => $amount,
                    'kredit'     => 0,
                ]);

                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id'     => $coaBeban->id,
                    'kode_coa'   => $coaBeban->kode_akun,
                    'debit'      => 0,
                    'kredit'     => $amount,
                ]);
            }

            return $journal;
        });
    }
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
     * Create journal entries when an invoice is marked as PAID.
     * Debit:  1010 Kas & Bank       (grand_total)
     * Kredit: 4010 Pendapatan Penjualan (grand_total)
     */
    public function createInvoicePaidJournal(Invoice $invoice): ?GeneralJournal
    {
        return DB::transaction(function () use ($invoice) {
            $coaKas        = ChartOfAccount::where('kode_akun', '1010')->first();
            $coaPendapatan = ChartOfAccount::where('kode_akun', '4010')->first();

            // Gracefully skip if COA accounts aren't set up yet
            if (!$coaKas || !$coaPendapatan) {
                return null;
            }

            $amount = (int) $invoice->grand_total;

            $journal = GeneralJournal::create([
                'no_bukti'     => $this->generateNoBukti('INV'),
                'keterangan'   => "Penjualan Invoice #{$invoice->invoice_number}",
                'reference_id' => $invoice->id,
                'source_type'  => 'INVOICE',
            ]);

            // Debit: Kas & Bank
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id'     => $coaKas->id,
                'kode_coa'   => $coaKas->kode_akun,
                'debit'      => $amount,
                'kredit'     => 0,
            ]);

            // Kredit: Pendapatan Penjualan
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id'     => $coaPendapatan->id,
                'kode_coa'   => $coaPendapatan->kode_akun,
                'debit'      => 0,
                'kredit'     => $amount,
            ]);

            return $journal;
        });
    }

    /**
     * Create reversal journal when a PAID invoice is cancelled or refunded.
     * Reverses the paid journal:
     * Debit:  4010 Pendapatan Penjualan (grand_total)
     * Kredit: 1010 Kas & Bank           (grand_total)
     */
    public function createInvoiceReversalJournal(Invoice $invoice, string $reason = 'cancelled'): ?GeneralJournal
    {
        return DB::transaction(function () use ($invoice, $reason) {
            $coaKas        = ChartOfAccount::where('kode_akun', '1010')->first();
            $coaPendapatan = ChartOfAccount::where('kode_akun', '4010')->first();

            if (!$coaKas || !$coaPendapatan) {
                return null;
            }

            $amount = (int) $invoice->grand_total;
            $label  = $reason === 'refunded' ? 'Refund' : 'Pembatalan';

            $journal = GeneralJournal::create([
                'no_bukti'     => $this->generateNoBukti('REV'),
                'keterangan'   => "{$label} Invoice #{$invoice->invoice_number}",
                'reference_id' => $invoice->id,
                'source_type'  => 'INVOICE',
            ]);

            // Debit: Pendapatan Penjualan (reverse)
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id'     => $coaPendapatan->id,
                'kode_coa'   => $coaPendapatan->kode_akun,
                'debit'      => $amount,
                'kredit'     => 0,
            ]);

            // Kredit: Kas & Bank (reverse)
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id'     => $coaKas->id,
                'kode_coa'   => $coaKas->kode_akun,
                'debit'      => 0,
                'kredit'     => $amount,
            ]);

            return $journal;
        });
    }

    /**
     * Create journal entries for a manual stock adjustment.
     * Stock In:  Debit 1030 Persediaan, Kredit 1010 Kas & Bank
     * Stock Out: Debit 5010 Beban Operasional, Kredit 1030 Persediaan
     */
    public function createStockMovementJournal(
        string $type,
        int $quantity,
        int $unitCost,
        string $productName,
        ?string $notes = null
    ): ?GeneralJournal {
        return DB::transaction(function () use ($type, $quantity, $unitCost, $productName, $notes) {
            $coaPersediaan = ChartOfAccount::where('kode_akun', '1030')->first();
            $coaKas        = ChartOfAccount::where('kode_akun', '1010')->first();
            $coaBeban      = ChartOfAccount::where('kode_akun', '5010')->first();

            if (!$coaPersediaan || !$coaKas || !$coaBeban) {
                return null;
            }

            $amount = $quantity * $unitCost;

            if ($amount <= 0) {
                return null;
            }

            $prefix = $type === 'in' ? 'STK-IN' : 'STK-OUT';
            $label  = $type === 'in' ? 'Stock In' : 'Stock Out';
            $description = "{$label}: {$productName} ({$quantity} unit)";
            if ($notes) {
                $description .= " — {$notes}";
            }

            $journal = GeneralJournal::create([
                'no_bukti'     => $this->generateNoBukti($prefix),
                'keterangan'   => $description,
                'reference_id' => null,
                'source_type'  => 'STOCK',
            ]);

            if ($type === 'in') {
                // Stock In: Debit Persediaan, Kredit Kas
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id'     => $coaPersediaan->id,
                    'kode_coa'   => $coaPersediaan->kode_akun,
                    'debit'      => $amount,
                    'kredit'     => 0,
                ]);
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id'     => $coaKas->id,
                    'kode_coa'   => $coaKas->kode_akun,
                    'debit'      => 0,
                    'kredit'     => $amount,
                ]);
            } else {
                // Stock Out: Debit Beban Operasional, Kredit Persediaan
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id'     => $coaBeban->id,
                    'kode_coa'   => $coaBeban->kode_akun,
                    'debit'      => $amount,
                    'kredit'     => 0,
                ]);
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id'     => $coaPersediaan->id,
                    'kode_coa'   => $coaPersediaan->kode_akun,
                    'debit'      => 0,
                    'kredit'     => $amount,
                ]);
            }

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
