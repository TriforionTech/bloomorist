<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalItem;
use Illuminate\Support\Collection;

class CashFlowService
{
    private const CASH_CODES = ['1101', '1102', '1010'];

    public function getForPeriod(int $periodId): array
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $lines = [
            'operating' => [
                ['key' => 'sales_receipts', 'label' => 'Penerimaan dari Penjualan', 'patterns' => ['4101']],
                ['key' => 'receivables', 'label' => 'Penerimaan Pelunasan Piutang', 'patterns' => ['1103']],
                ['key' => 'purchases', 'label' => 'Pembayaran Pembelian Bunga', 'patterns' => ['51*']],
                ['key' => 'payables', 'label' => 'Pembayaran Hutang Dagang', 'patterns' => ['2101']],
                ['key' => 'operating_expenses', 'label' => 'Pembayaran Beban Operasional', 'patterns' => ['6*']],
                ['key' => 'interest_income', 'label' => 'Penerimaan Pendapatan Bunga', 'patterns' => ['4103']],
                ['key' => 'other_income', 'label' => 'Penerimaan Pendapatan Lain-Lain', 'patterns' => ['4104']],
            ],
            'investing' => [
                ['key' => 'fixed_assets', 'label' => 'Pembelian Peralatan', 'patterns' => ['1105']],
                ['key' => 'investment_receivables', 'label' => 'Pinjaman/Piutang Investasi', 'patterns' => ['1109']],
                ['key' => 'land_advances', 'label' => 'Uang Muka Pembelian Tanah', 'patterns' => ['1112']],
            ],
            'financing' => [
                ['key' => 'owner_capital', 'label' => 'Setoran Modal Pemilik', 'patterns' => ['3101']],
                ['key' => 'owner_drawings', 'label' => 'Prive Pemilik', 'patterns' => ['3102']],
            ],
        ];

        $result = [];
        foreach ($lines as $section => $sectionLines) {
            $result[$section] = collect($sectionLines)->map(function (array $line) use ($period): array {
                $line['amount'] = $this->cashFlowLine($period, $line['patterns']);
                return $line;
            });
            $result["{$section}_total"] = $result[$section]->sum('amount');
        }

        $netChange = $result['operating_total'] + $result['investing_total'] + $result['financing_total'];
        $openingCash = (float) $period->opening_cash_balance;
        $endingCash = $openingCash + $netChange;
        // Reconcile both ledgers that already contain opening transactions and
        // test/import ledgers that only contain movements inside this period.
        $priorCash = $this->cashBalanceBeforePeriod($period);
        $cashBalance = $this->cashBalanceAtPeriodEnd($period) + $openingCash - $priorCash;

        return [
            ...$result,
            'opening_cash' => $openingCash,
            'net_change' => $netChange,
            'ending_cash' => $endingCash,
            'trial_balance_cash' => $cashBalance,
            'is_reconciled' => abs($endingCash - $cashBalance) < 0.01,
            'difference' => $endingCash - $cashBalance,
            'period' => $period,
        ];
    }

    public function cashFlowLine(AccountingPeriod $period, array $patterns): float
    {
        $cashAccountIds = ChartOfAccount::whereIn('kode_akun', self::CASH_CODES)->pluck('id');
        if ($cashAccountIds->isEmpty()) {
            return 0;
        }

        // Get all journal IDs that have cash movements in this period
        $journalIdsWithCash = JournalItem::query()
            ->whereIn('coa_id', $cashAccountIds)
            ->whereHas('journal', function ($query) use ($period) {
                $query->whereDate('tanggal', '>=', $period->start_date)
                    ->whereDate('tanggal', '<=', $period->end_date);
            })
            ->pluck('journal_id')
            ->unique();

        if ($journalIdsWithCash->isEmpty()) {
            return 0;
        }

        // Now sum the (kredit - debit) of the counter accounts that match our patterns
        return (float) JournalItem::query()
            ->with('coa')
            ->whereIn('journal_id', $journalIdsWithCash)
            ->whereNotIn('coa_id', $cashAccountIds) // Only look at non-cash lines
            ->get()
            ->filter(fn (JournalItem $line) => $this->matches($line->coa?->kode_akun, $patterns))
            ->sum(fn (JournalItem $line) => $line->kredit - $line->debit);
    }

    private function cashBalanceAtPeriodEnd(AccountingPeriod $period): float
    {
        $cashAccountIds = ChartOfAccount::whereIn('kode_akun', self::CASH_CODES)->pluck('id');

        return (float) JournalItem::whereIn('coa_id', $cashAccountIds)
            ->whereHas('journal', fn ($query) => $query->whereDate('tanggal', '<=', $period->end_date))
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(kredit), 0) AS balance')
            ->value('balance');
    }

    private function cashBalanceBeforePeriod(AccountingPeriod $period): float
    {
        $cashAccountIds = ChartOfAccount::whereIn('kode_akun', self::CASH_CODES)->pluck('id');

        return (float) JournalItem::whereIn('coa_id', $cashAccountIds)
            ->whereHas('journal', fn ($query) => $query->whereDate('tanggal', '<', $period->start_date))
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(kredit), 0) AS balance')
            ->value('balance');
    }

    private function matches(?string $code, array $patterns): bool
    {
        if ($code === null) {
            return false;
        }

        foreach ($patterns as $pattern) {
            $regex = '/^' . str_replace(['*', '/'], ['.*', '\/'], $pattern) . '$/';
            if (preg_match($regex, $code) === 1) {
                return true;
            }
        }

        return false;
    }
}
