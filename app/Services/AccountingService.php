<?php

namespace App\Services;

use App\Models\AccountingPeriod;
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
     *
     * Untuk kerugian stok (loss/out):
     *   Debit:  5010 Beban Operasional (seluruh kerusakan/kerugian dibebankan ke operasional)
     *   Kredit: 1030 Persediaan Barang
     *
     * Untuk koreksi positif stok (in):
     *   Debit:  1030 Persediaan Barang
     *   Kredit: 5010 Beban Operasional (koreksi / pembatalan beban)
     */
    public function createStockAdjustmentJournal(\App\Models\Product $product, int $quantity, string $type, string $notes = ''): ?GeneralJournal
    {
        return DB::transaction(function () use ($product, $quantity, $type, $notes) {
            // Find COA Persediaan Barang
            $coaPersediaan = ChartOfAccount::where('kode_akun', '1104')->first();

            // Find COA Beban Operasional (semua kerugian/kerusakan masuk beban operasional)
            $coaBeban = ChartOfAccount::where('kode_akun', '6106')->first();

            // Gracefully skip if COA accounts aren't set up yet
            if (! $coaPersediaan || ! $coaBeban) {
                return null;
            }

            $amount = (int) ($product->harga_beli * $quantity);

            if ($amount <= 0) {
                return null;
            }

            $journal = GeneralJournal::create([
                'tanggal' => now()->toDateString(),
                'no_bukti' => $this->generateNoBukti('ADJ'),
                'keterangan' => "Penyesuaian Stok ({$type}) - {$product->nama} - {$notes}",
                'reference_id' => $product->id,
                'source_type' => 'STOCK_ADJUSTMENT',
            ]);

            if ($type === 'loss' || $type === 'out') {
                // Barang Hilang/Rusak: Debit Beban Operasional, Kredit Persediaan
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id' => $coaBeban->id,
                    'kode_coa' => $coaBeban->kode_akun,
                    'debit' => $amount,
                    'kredit' => 0,
                ]);

                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id' => $coaPersediaan->id,
                    'kode_coa' => $coaPersediaan->kode_akun,
                    'debit' => 0,
                    'kredit' => $amount,
                ]);
            } else {
                // Barang Masuk (Opname Plus): Debit Persediaan, Kredit Beban Operasional
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id' => $coaPersediaan->id,
                    'kode_coa' => $coaPersediaan->kode_akun,
                    'debit' => $amount,
                    'kredit' => 0,
                ]);

                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id' => $coaBeban->id,
                    'kode_coa' => $coaBeban->kode_akun,
                    'debit' => 0,
                    'kredit' => $amount,
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

        return $pattern.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create journal entries automatically from an Expense record.
     * Debit: COA Beban (expense->coa_id)
     * Kredit: COA Kas/Bank (expense->coa_kredit_id, user-selected)
     *
     * Idempotent: jika journal EXPENSE sudah ada untuk reference_id ini, skip.
     */
    public function createExpenseJournal(Expense $expense): ?GeneralJournal
    {
        return DB::transaction(function () use ($expense) {
            // Idempotency: cek apakah sudah pernah dibuat
            $existing = GeneralJournal::where('reference_id', $expense->id)
                ->where('source_type', 'EXPENSE')
                ->whereNot(function ($q) {
                    $q->where('keterangan', 'like', 'Pembatalan Expense:%');
                })
                ->first();

            if ($existing) {
                return $existing;
            }

            $coaBeban = ChartOfAccount::findOrFail($expense->coa_id);
            $coaKredit = ChartOfAccount::findOrFail($expense->coa_kredit_id);

            $journal = GeneralJournal::create([
                'tanggal' => $expense->created_at->toDateString(),
                'no_bukti' => $this->generateNoBukti('EXP'),
                'keterangan' => $expense->keterangan,
                'reference_id' => $expense->id,
                'source_type' => 'EXPENSE',
            ]);

            // Sync journal date to expense date
            $journal->created_at = $expense->created_at;
            $journal->save(['timestamps' => false]);

            // Debit: Akun Beban
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id' => $coaBeban->id,
                'kode_coa' => $coaBeban->kode_akun,
                'debit' => $expense->nominal,
                'kredit' => 0,
            ]);

            // Kredit: Akun Kas/Bank (user-selected)
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id' => $coaKredit->id,
                'kode_coa' => $coaKredit->kode_akun,
                'debit' => 0,
                'kredit' => $expense->nominal,
            ]);

            return $journal;
        });
    }

    /**
     * Create reversing journal entry for a posted expense.
     *
     * TIDAK menghapus jurnal asli.
     * Posisi Debit/Kredit dibalik sehingga saldo kembali netral.
     *
     * Debit:  COA Kas/Bank (sebelumnya di kredit)
     * Kredit: COA Beban (sebelumnya di debit)
     */
    public function createExpenseReversalJournal(Expense $expense): ?GeneralJournal
    {
        return DB::transaction(function () use ($expense) {
            $coaBeban = ChartOfAccount::findOrFail($expense->coa_id);
            $coaKredit = ChartOfAccount::findOrFail($expense->coa_kredit_id);

            $journal = GeneralJournal::create([
                'tanggal' => $expense->created_at->toDateString(),
                'no_bukti' => $this->generateNoBukti('REV'),
                'keterangan' => "Pembatalan Expense: {$expense->keterangan}",
                'reference_id' => $expense->id,
                'source_type' => 'EXPENSE',
            ]);

            // Sync journal date to expense date
            $journal->created_at = $expense->created_at;
            $journal->save(['timestamps' => false]);

            // Debit: Akun Kas/Bank (reverse — sebelumnya di kredit)
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id' => $coaKredit->id,
                'kode_coa' => $coaKredit->kode_akun,
                'debit' => $expense->nominal,
                'kredit' => 0,
            ]);

            // Kredit: Akun Beban (reverse — sebelumnya di debit)
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id' => $coaBeban->id,
                'kode_coa' => $coaBeban->kode_akun,
                'debit' => 0,
                'kredit' => $expense->nominal,
            ]);

            return $journal;
        });
    }

    /**
     * Create journal entries when an invoice is marked as PAID.
     * Debit:  1010 Kas & Bank       (grand_total)
     * Kredit: 4010 Pendapatan Penjualan (grand_total)
     */
    /**
     * Idempotent: jika journal INVOICE (non-reversal) sudah ada untuk
     * reference_id ini, skip untuk mencegah duplicate posting.
     */
    public function createInvoicePaidJournal(Invoice $invoice): ?GeneralJournal
    {
        return DB::transaction(function () use ($invoice) {
            // Idempotency: cek apakah sudah pernah dibuat
            $existing = GeneralJournal::where('reference_id', $invoice->id)
                ->where('source_type', 'INVOICE')
                ->where('keterangan', 'like', 'Penjualan Invoice%')
                ->first();

            if ($existing) {
                return $existing;
            }

            $coaKas = ChartOfAccount::where('kode_akun', '1101')->first();
            $coaPendapatan = ChartOfAccount::where('kode_akun', '4101')->first();

            // Gracefully skip if COA accounts aren't set up yet
            if (! $coaKas || ! $coaPendapatan) {
                return null;
            }

            $amount = (int) $invoice->grand_total;

            $journal = GeneralJournal::create([
                'tanggal' => $invoice->issued_date->toDateString(),
                'no_bukti' => $this->generateNoBukti('INV'),
                'keterangan' => "Penjualan Invoice #{$invoice->invoice_number}",
                'reference_id' => $invoice->id,
                'source_type' => 'INVOICE',
            ]);

            // Sync journal date to invoice date
            $journal->created_at = $invoice->issued_date;
            $journal->save(['timestamps' => false]);

            // Debit: Kas & Bank
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id' => $coaKas->id,
                'kode_coa' => $coaKas->kode_akun,
                'debit' => $amount,
                'kredit' => 0,
            ]);

            // Kredit: Pendapatan Penjualan
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id' => $coaPendapatan->id,
                'kode_coa' => $coaPendapatan->kode_akun,
                'debit' => 0,
                'kredit' => $amount,
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
        return DB::transaction(function () use ($invoice) {
            $coaKas = ChartOfAccount::where('kode_akun', '1101')->first();
            $coaPendapatan = ChartOfAccount::where('kode_akun', '4101')->first();

            if (! $coaKas || ! $coaPendapatan) {
                return null;
            }

            $amount = (int) $invoice->grand_total;

            $journal = GeneralJournal::create([
                'tanggal' => $invoice->issued_date->toDateString(),
                'no_bukti' => $this->generateNoBukti('REV'),
                'keterangan' => "Pembatalan Invoice #{$invoice->invoice_number}",
                'reference_id' => $invoice->id,
                'source_type' => 'INVOICE',
            ]);

            // Sync journal date to invoice date
            $journal->created_at = $invoice->issued_date;
            $journal->save(['timestamps' => false]);

            // Debit: Pendapatan Penjualan (reverse)
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id' => $coaPendapatan->id,
                'kode_coa' => $coaPendapatan->kode_akun,
                'debit' => $amount,
                'kredit' => 0,
            ]);

            // Kredit: Kas & Bank (reverse)
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id' => $coaKas->id,
                'kode_coa' => $coaKas->kode_akun,
                'debit' => 0,
                'kredit' => $amount,
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
            $coaPersediaan = ChartOfAccount::where('kode_akun', '1104')->first();
            $coaKas = ChartOfAccount::where('kode_akun', '1101')->first();
            $coaBeban = ChartOfAccount::where('kode_akun', '6106')->first();

            if (! $coaPersediaan || ! $coaKas || ! $coaBeban) {
                return null;
            }

            $amount = $quantity * $unitCost;

            if ($amount <= 0) {
                return null;
            }

            $prefix = $type === 'in' ? 'STK-IN' : 'STK-OUT';
            $label = $type === 'in' ? 'Stock In' : 'Stock Out';
            $description = "{$label}: {$productName} ({$quantity} unit)";
            if ($notes) {
                $description .= " — {$notes}";
            }

            $journal = GeneralJournal::create([
                'tanggal' => now()->toDateString(),
                'no_bukti' => $this->generateNoBukti($prefix),
                'keterangan' => $description,
                'reference_id' => null,
                'source_type' => 'STOCK',
            ]);

            if ($type === 'in') {
                // Stock In: Debit Persediaan, Kredit Kas
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id' => $coaPersediaan->id,
                    'kode_coa' => $coaPersediaan->kode_akun,
                    'debit' => $amount,
                    'kredit' => 0,
                ]);
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id' => $coaKas->id,
                    'kode_coa' => $coaKas->kode_akun,
                    'debit' => 0,
                    'kredit' => $amount,
                ]);
            } else {
                // Stock Out: Debit Beban Operasional, Kredit Persediaan
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id' => $coaBeban->id,
                    'kode_coa' => $coaBeban->kode_akun,
                    'debit' => $amount,
                    'kredit' => 0,
                ]);
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id' => $coaPersediaan->id,
                    'kode_coa' => $coaPersediaan->kode_akun,
                    'debit' => 0,
                    'kredit' => $amount,
                ]);
            }

            return $journal;
        });
    }

    /**
     * Create journal entries for a stock transfer.
     * Records the transfer between two products and records any price variance as gain/loss.
     */
    public function createStockTransferJournal(\App\Models\StockTransfer $transfer): ?GeneralJournal
    {
        return DB::transaction(function () use ($transfer) {
            $coaPersediaan = ChartOfAccount::where('kode_akun', '1104')->first();
            $coaPendapatanLain = ChartOfAccount::where('kode_akun', '4104')->first();
            $coaBebanLain = ChartOfAccount::where('kode_akun', '6108')->first();

            if (! $coaPersediaan || ! $coaPendapatanLain || ! $coaBebanLain) {
                return null;
            }

            $sourceProduct = $transfer->sourceProduct;
            $targetProduct = $transfer->targetProduct;
            $quantity = $transfer->quantity;

            $sourceValue = $quantity * $sourceProduct->harga_beli;
            $targetValue = $quantity * $targetProduct->harga_beli;

            $journal = GeneralJournal::create([
                'tanggal' => $transfer->tanggal->toDateString(),
                'no_bukti' => $this->generateNoBukti('TRF'),
                'keterangan' => "Transfer Stok: {$quantity} {$sourceProduct->nama} ke {$targetProduct->nama}".($transfer->keterangan ? " - {$transfer->keterangan}" : ''),
                'reference_id' => $transfer->id,
                'source_type' => 'STOCK_TRANSFER',
            ]);

            // Sync journal date
            $journal->created_at = $transfer->tanggal;
            $journal->save(['timestamps' => false]);

            // Debit target inventory
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id' => $coaPersediaan->id,
                'kode_coa' => $coaPersediaan->kode_akun,
                'debit' => $targetValue,
                'kredit' => 0,
            ]);

            // Credit source inventory
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id' => $coaPersediaan->id,
                'kode_coa' => $coaPersediaan->kode_akun,
                'debit' => 0,
                'kredit' => $sourceValue,
            ]);

            $variance = $targetValue - $sourceValue;

            if ($variance > 0) {
                // Gain -> Credit Pendapatan Lain-lain
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id' => $coaPendapatanLain->id,
                    'kode_coa' => $coaPendapatanLain->kode_akun,
                    'debit' => 0,
                    'kredit' => $variance,
                ]);
            } elseif ($variance < 0) {
                // Loss -> Debit Beban Lain-lain
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'coa_id' => $coaBebanLain->id,
                    'kode_coa' => $coaBebanLain->kode_akun,
                    'debit' => abs($variance),
                    'kredit' => 0,
                ]);
            }

            // Deduct stock from source
            $sourceProduct->decrement('stok', $quantity);
            // Add stock to target
            $targetProduct->increment('stok', $quantity);

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
                $q->where('tanggal', '<', $beforeDate->toDateString());
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
                    $q->where('tanggal', '>=', $startDate->toDateString());
                }
                if ($endDate) {
                    $q->where('tanggal', '<=', $endDate->toDateString());
                }
            })
            ->join('bl_general_journals_t', 'bl_journal_items_t.journal_id', '=', 'bl_general_journals_t.id')
            ->orderBy('bl_general_journals_t.tanggal', 'asc')
            ->orderBy('bl_general_journals_t.id', 'asc')
            ->select('bl_journal_items_t.*')
            ->get();

        // Build rows with running balance
        $rows = collect();
        $runningBalance = $openingBalance;

        // Add opening balance row
        $rows->push([
            'tanggal' => $startDate?->format('d/m/Y') ?? '-',
            'no_bukti' => '-',
            'kode_coa' => $coa->kode_akun,
            'keterangan' => 'Saldo Awal',
            'debit' => 0,
            'kredit' => 0,
            'saldo' => $runningBalance,
            'is_opening' => true,
        ]);

        foreach ($query as $item) {
            if ($coa->isDebitNormal()) {
                $runningBalance += ($item->debit - $item->kredit);
            } else {
                $runningBalance += ($item->kredit - $item->debit);
            }

            $rows->push([
                'tanggal' => $item->journal->tanggal ? $item->journal->tanggal->format('d/m/Y') : $item->journal->created_at->format('d/m/Y'),
                'no_bukti' => $item->journal->no_bukti,
                'kode_coa' => $item->kode_coa,
                'keterangan' => $item->journal->keterangan,
                'debit' => $item->debit,
                'kredit' => $item->kredit,
                'saldo' => $runningBalance,
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
        $pendapatan = $this->getAccountGroupTotals('Pendapatan', $start, $end, true);
        $beban = $this->getAccountGroupTotals('Beban', $start, $end, true);
        $totalPendapatan = $pendapatan->sum('saldo');
        $totalBeban = $beban->sum('saldo');

        $sales = $this->accountBalanceByCode('4101', $start, $end, true);
        $salesReturns = $this->accountBalanceByCode('4102', $start, $end, true);
        $interestIncome = $this->accountBalanceByCode('4103', $start, $end, true);
        $otherIncome = $this->accountBalanceByCode('4104', $start, $end, true);
        $hasPeriodicAccounts = ChartOfAccount::whereIn('kode_akun', ['4101', '4102', '5101', '5102', '5103', '1104'])->exists();

        $netSales = $hasPeriodicAccounts ? $sales - $salesReturns : $totalPendapatan;
        $netPurchases = $this->accountBalanceByCode('5101', $start, $end, true)
            - $this->accountBalanceByCode('5102', $start, $end, true);
        $purchaseFreight = $this->accountBalanceByCode('5103', $start, $end, true);
        // Use the ledger balance of inventory up to the end date (Persediaan Buku).
        // This ensures any manual STK adjustments or initial capital journals placed mid-period
        // are properly absorbed into COGS, guaranteeing the Balance Sheet remains perfectly balanced.
        $openingInventory = $this->accountBalanceBeforeCode('1104', $end->copy()->addDay());
        $closingInventory = $this->findPeriodForRange($start, $end)?->closing_inventory_value;
        $goodsAvailable = $openingInventory + $netPurchases + $purchaseFreight;
        $cogs = $hasPeriodicAccounts && $closingInventory !== null
            ? $goodsAvailable - (float) $closingInventory
            : 0;

        $operatingExpenses = $hasPeriodicAccounts
            ? $totalBeban - $this->accountBalanceByCode('5101', $start, $end, true)
                - $this->accountBalanceByCode('5102', $start, $end, true)
                - $purchaseFreight
            : $totalBeban;
        $grossProfit = $netSales - $cogs;
        $operatingProfit = $grossProfit - $operatingExpenses;
        $outsideOperatingIncome = $hasPeriodicAccounts
            ? $interestIncome + $otherIncome
            : 0;
        $labaRugi = $hasPeriodicAccounts
            ? $operatingProfit + $outsideOperatingIncome
            : $totalPendapatan - $totalBeban;

        // Create a lookup table for all account balances in this period for easy Blade templating
        $balancesByCode = $pendapatan->concat($beban)->pluck('saldo', 'kode_akun')->toArray();

        return [
            'pendapatan' => $pendapatan,
            'total_pendapatan' => $hasPeriodicAccounts ? $netSales + $outsideOperatingIncome : $totalPendapatan,
            'beban' => $beban,
            'total_beban' => $hasPeriodicAccounts ? $cogs + $operatingExpenses : $totalBeban,
            'laba_rugi' => $labaRugi,
            'penjualan_bersih' => $netSales,
            'persediaan_awal' => $openingInventory,
            'pembelian_bersih' => $netPurchases,
            'beban_angkut_pembelian' => $purchaseFreight,
            'barang_tersedia_dijual' => $goodsAvailable,
            'persediaan_akhir' => $closingInventory,
            'hpp' => $cogs,
            'laba_kotor' => $grossProfit,
            'beban_operasional' => $operatingExpenses,
            'laba_usaha' => $operatingProfit,
            'pendapatan_luar_usaha' => $outsideOperatingIncome,
            'start_date' => $start,
            'end_date' => $end,
            'balances_by_code' => $balancesByCode,
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
        $period = AccountingPeriod::query()
            ->whereDate('start_date', '<=', $asOf->toDateString())
            ->whereDate('end_date', '>=', $asOf->toDateString())
            ->first();
        if ($period?->closing_inventory_value !== null) {
            $aset = $aset->map(function (array $row) use ($period): array {
                if ($row['kode_akun'] === '1104') {
                    $row['saldo'] = (float) $period->closing_inventory_value;
                }

                return $row;
            });
        }
        // Kewajiban
        $kewajiban = $this->getAccountGroupTotals('Kewajiban', null, $endOfDay);

        // Ekuitas
        $ekuitas = $this->getAccountGroupTotals('Ekuitas', null, $endOfDay);
        $categoryByCode = ChartOfAccount::query()
            ->whereIn('kode_akun', $aset->pluck('kode_akun')
                ->merge($kewajiban->pluck('kode_akun'))
                ->merge($ekuitas->pluck('kode_akun')))
            ->pluck('kategori', 'kode_akun');

        // Kontra aset dan kontra modal mengurangi saldo kelompoknya.
        $aset = $aset->map(function (array $row) use ($categoryByCode): array {
            if ($categoryByCode->get($row['kode_akun']) === 'Aktiva Tetap (Kontra)') {
                $row['saldo'] *= -1;
            }

            return $row;
        });
        $totalAset = $aset->sum('saldo');

        $ekuitas = $ekuitas->map(function (array $row) use ($categoryByCode): array {
            if ($categoryByCode->get($row['kode_akun']) === 'Modal (Kontra)') {
                $row['saldo'] *= -1;
            }

            return $row;
        });
        $totalEkuitasMurni = $ekuitas->sum('saldo');
        $totalKewajiban = $kewajiban->sum('saldo');

        // Include the periodic inventory adjustment in current-period income.
        $labaDitahan = $this->getRetainedEarnings($endOfDay);
        if ($period) {
            $periodStart = Carbon::parse(
                $period->getRawOriginal('start_date'),
                config('app.timezone'),
            )->startOfDay();
            $priorRetainedEarnings = $this->getRetainedEarnings($periodStart->copy()->subSecond());
            $currentIncome = $this->getIncomeStatement(
                $periodStart,
                Carbon::parse($period->getRawOriginal('end_date'), config('app.timezone'))->endOfDay(),
            )['laba_rugi'];
            $labaDitahan = $priorRetainedEarnings + $currentIncome;
        }

        $totalEkuitas = $totalEkuitasMurni + $labaDitahan;
        $totalKewajibanEkuitas = $totalKewajiban + $totalEkuitas;

        $balancesByCode = $aset->concat($kewajiban)->concat($ekuitas)->pluck('saldo', 'kode_akun')->toArray();

        return [
            'aset' => $aset,
            'aset_groups' => $this->groupBalanceItems($aset, $categoryByCode, ['Aktiva Lancar', 'Aktiva Tetap', 'Aktiva Tetap (Kontra)']),
            'total_aset' => $totalAset,
            'kewajiban' => $kewajiban,
            'kewajiban_groups' => $this->groupBalanceItems($kewajiban, $categoryByCode, ['Kewajiban Lancar']),
            'total_kewajiban' => $totalKewajiban,
            'ekuitas' => $ekuitas,
            'ekuitas_groups' => $this->groupBalanceItems($ekuitas, $categoryByCode, ['Modal', 'Modal (Kontra)']),
            'total_ekuitas_murni' => $totalEkuitasMurni,
            'laba_ditahan' => $labaDitahan,
            'total_ekuitas' => $totalEkuitas,
            'total_kewajiban_ekuitas' => $totalKewajibanEkuitas,
            'is_balanced' => abs((float) $totalAset - (float) $totalKewajibanEkuitas) < 0.01,
            'as_of' => $asOf,
            'balances_by_code' => $balancesByCode,
        ];
    }

    private function groupBalanceItems(Collection $items, Collection $categoryByCode, array $categories): array
    {
        return collect($categories)->mapWithKeys(function (string $category) use ($items, $categoryByCode): array {
            return [$category => $items
                ->filter(fn (array $item): bool => $categoryByCode->get($item['kode_akun']) === $category)
                ->values()
                ->all()];
        })->all();
    }

    /**
     * Calculate Retained Earnings (Laba Ditahan) from system inception to a date.
     * = Total Pendapatan - Total Beban (all time up to date)
     */
    public function getRetainedEarnings(Carbon $asOf): int
    {
        $pendapatan = $this->getAccountGroupTotals('Pendapatan', null, $asOf);
        $beban = $this->getAccountGroupTotals('Beban', null, $asOf);

        return $pendapatan->sum('saldo') - $beban->sum('saldo');
    }

    private function accountBalanceByCode(string $code, ?Carbon $start, ?Carbon $end, bool $excludeClosing = false): float
    {
        $account = ChartOfAccount::where('kode_akun', $code)->first();
        if (! $account) {
            return 0;
        }

        $totals = JournalItem::where('coa_id', $account->id)
            ->whereHas('journal', function ($query) use ($start, $end, $excludeClosing) {
                if ($start) {
                    $query->whereDate('tanggal', '>=', $start->toDateString());
                }
                if ($end) {
                    $query->whereDate('tanggal', '<=', $end->toDateString());
                }
                if ($excludeClosing) {
                    $query->where('source_type', '!=', 'CLOSING');
                }
            })
            ->selectRaw('COALESCE(SUM(debit), 0) AS total_debit, COALESCE(SUM(kredit), 0) AS total_kredit')
            ->first();

        return $account->isDebitNormal()
            ? (float) $totals->total_debit - (float) $totals->total_kredit
            : (float) $totals->total_kredit - (float) $totals->total_debit;
    }

    private function accountBalanceBeforeCode(string $code, Carbon $date): float
    {
        $account = ChartOfAccount::where('kode_akun', $code)->first();
        if (! $account) {
            return 0;
        }

        $totals = JournalItem::where('coa_id', $account->id)
            ->whereHas('journal', fn ($query) => $query->whereDate('tanggal', '<=', $date->copy()->subDay()->toDateString()))
            ->selectRaw('COALESCE(SUM(debit), 0) AS total_debit, COALESCE(SUM(kredit), 0) AS total_kredit')
            ->first();

        return $account->isDebitNormal()
            ? (float) $totals->total_debit - (float) $totals->total_kredit
            : (float) $totals->total_kredit - (float) $totals->total_debit;
    }

    private function findPeriodForRange(Carbon $start, Carbon $end): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereDate('start_date', '<=', $start->toDateString())
            ->whereDate('end_date', '>=', $end->toDateString())
            ->first();
    }

    /**
     * Get totals for all accounts in a category within a date range.
     * Each account's saldo is computed respecting its saldo_normal direction.
     */
    private function getAccountGroupTotals(string $kategori, ?Carbon $start, ?Carbon $end, bool $excludeClosing = false): Collection
    {
        $accounts = ChartOfAccount::query()
            ->when($kategori === 'Aset', fn ($query) => $query->where(function ($q) {
                $q->where('kategori', 'Aset')->orWhere('kategori', 'like', 'Aktiva%');
            }))
            ->when($kategori === 'Kewajiban', fn ($query) => $query->where(function ($q) {
                $q->where('kategori', 'Kewajiban')->orWhere('kategori', 'like', 'Kewajiban%');
            }))
            ->when($kategori === 'Ekuitas', fn ($query) => $query->where(function ($q) {
                $q->whereIn('kategori', ['Ekuitas', 'Modal'])->orWhere('kategori', 'like', 'Modal%');
            }))
            ->when($kategori === 'Pendapatan', fn ($query) => $query->where('kategori', 'like', 'Pendapatan%'))
            ->when($kategori === 'Beban', fn ($query) => $query->where(function ($q) {
                $q->where('kategori', 'Beban')->orWhere('kategori', 'like', 'Beban%');
            }))
            ->orderBy('kode_akun')
            ->get();

        return $accounts->map(function ($account) use ($start, $end, $excludeClosing) {
            $query = JournalItem::where('coa_id', $account->id)
                ->whereHas('journal', function ($q) use ($start, $end, $excludeClosing) {
                    if ($start) {
                        $q->where('tanggal', '>=', $start->toDateString());
                    }
                    if ($end) {
                        $q->where('tanggal', '<=', $end->toDateString());
                    }
                    if ($excludeClosing) {
                        $q->where('source_type', '!=', 'CLOSING');
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
                'saldo' => $saldo,
            ];
        });
    }
}
