<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralJournal;
use App\Models\JournalItem;
use App\Services\CashFlowService;
use App\Services\FinancialHealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_cash_flow_groups_cash_movements_by_counter_account(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'opening_cash_balance' => 1000,
            'status' => 'open',
        ]);
        $cash = $this->account('1010', 'Kas & Bank', 'Aset', 'Debit');
        $sales = $this->account('4101', 'Penjualan', 'Pendapatan', 'Kredit');
        $expense = $this->account('6101', 'Beban Gaji', 'Beban', 'Debit');
        $capital = $this->account('3101', 'Modal Pemilik', 'Ekuitas', 'Kredit');

        $this->journal($period, 'Penjualan tunai', [[$cash, 3000, 0], [$sales, 0, 3000]]);
        $this->journal($period, 'Pembayaran gaji', [[$expense, 500, 0], [$cash, 0, 500]]);
        $this->journal($period, 'Setoran modal', [[$cash, 1000, 0], [$capital, 0, 1000]]);

        $data = app(CashFlowService::class)->getForPeriod($period->id);

        $this->assertSame(3000.0, $data['operating']->firstWhere('key', 'sales_receipts')['amount']);
        $this->assertSame(-500.0, $data['operating']->firstWhere('key', 'operating_expenses')['amount']);
        $this->assertSame(1000.0, $data['financing']->firstWhere('key', 'owner_capital')['amount']);
        $this->assertSame(4500.0, $data['ending_cash']);
        $this->assertTrue($data['is_reconciled']);
        $this->assertTrue(app(FinancialHealthCheckService::class)->checkCashFlow($period->id)['is_healthy']);
    }

    private function account(string $code, string $name, string $category, string $normal): ChartOfAccount
    {
        return ChartOfAccount::create([
            'kode_akun' => $code,
            'nama_akun' => $name,
            'kategori' => $category,
            'saldo_normal' => $normal,
        ]);
    }

    private function journal(AccountingPeriod $period, string $description, array $lines): void
    {
        $journal = GeneralJournal::create([
            'tanggal' => $period->start_date,
            'no_bukti' => 'CF-' . md5($description),
            'keterangan' => $description,
            'source_type' => 'MANUAL',
        ]);

        foreach ($lines as [$account, $debit, $credit]) {
            JournalItem::create([
                'journal_id' => $journal->id,
                'coa_id' => $account->id,
                'kode_coa' => $account->kode_akun,
                'debit' => $debit,
                'kredit' => $credit,
            ]);
        }
    }
}
