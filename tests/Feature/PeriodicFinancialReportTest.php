<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralJournal;
use App\Models\JournalItem;
use App\Services\AccountingService;
use App\Services\FinancialHealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodicFinancialReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_statement_calculates_periodic_cogs_from_closing_inventory(): void
    {
        $priorPeriod = AccountingPeriod::create([
            'label' => 'Agustus 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'open',
            'closing_inventory_value' => 1000,
        ]);
        $period = AccountingPeriod::create([
            'label' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
            'closing_inventory_value' => 600,
        ]);

        $accounts = [];
        foreach ([
            ['1101', 'Kas', 'Aset', 'Debit'],
            ['1104', 'Persediaan Bunga', 'Aset', 'Debit'],
            ['3101', 'Modal Pemilik', 'Ekuitas', 'Kredit'],
            ['4101', 'Penjualan', 'Pendapatan', 'Kredit'],
            ['5101', 'Pembelian Bunga', 'Beban', 'Debit'],
            ['6101', 'Beban Gaji', 'Beban', 'Debit'],
        ] as [$code, $name, $category, $normal]) {
            $accounts[$code] = ChartOfAccount::create([
                'kode_akun' => $code,
                'nama_akun' => $name,
                'kategori' => $category,
                'saldo_normal' => $normal,
            ]);
        }

        $this->createJournal($priorPeriod, '2026-08-31', 'Saldo awal persediaan', [
            [$accounts['1104'], 1000, 0],
            [$accounts['3101'], 0, 1000],
        ]);
        $this->createJournal($period, '2026-09-10', 'Penjualan', [
            [$accounts['1101'], 3000, 0],
            [$accounts['4101'], 0, 3000],
        ]);
        $this->createJournal($period, '2026-09-11', 'Pembelian', [
            [$accounts['5101'], 1500, 0],
            [$accounts['1101'], 0, 1500],
        ]);
        $this->createJournal($period, '2026-09-12', 'Gaji', [
            [$accounts['6101'], 200, 0],
            [$accounts['1101'], 0, 200],
        ]);
        $priorPeriod->update(['status' => 'closed', 'closed_at' => now()]);

        $report = app(AccountingService::class)->getIncomeStatement(
            $period->start_date->startOfDay(),
            $period->end_date->endOfDay(),
        );

        $this->assertSame(3000.0, $report['penjualan_bersih']);
        $this->assertSame(1000.0, $report['persediaan_awal']);
        $this->assertSame(1500.0, $report['pembelian_bersih']);
        $this->assertSame(1900.0, $report['hpp']);
        $this->assertSame(1100.0, $report['laba_kotor']);
        $this->assertSame(900.0, $report['laba_rugi']);
    }

    public function test_balance_sheet_uses_closing_inventory_for_period(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
            'closing_inventory_value' => 600,
        ]);
        $inventory = ChartOfAccount::create([
            'kode_akun' => '1104',
            'nama_akun' => 'Persediaan Bunga',
            'kategori' => 'Aset',
            'saldo_normal' => 'Debit',
        ]);
        $journal = GeneralJournal::create([
            'tanggal' => '2026-09-10',
            'no_bukti' => 'JU-INV-001',
            'keterangan' => 'Saldo persediaan',
            'source_type' => 'MANUAL',
        ]);
        JournalItem::create([
            'journal_id' => $journal->id,
            'coa_id' => $inventory->id,
            'kode_coa' => '1104',
            'debit' => 1000,
            'kredit' => 0,
        ]);

        $report = app(AccountingService::class)->getBalanceSheet($period->end_date);

        $this->assertSame(600.0, $report['aset']->firstWhere('kode_akun', '1104')['saldo']);
    }

    public function test_health_check_validates_balance_sheet(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);
        $cash = ChartOfAccount::create([
            'kode_akun' => '1101',
            'nama_akun' => 'Kas',
            'kategori' => 'Aset',
            'saldo_normal' => 'Debit',
        ]);
        $capital = ChartOfAccount::create([
            'kode_akun' => '3101',
            'nama_akun' => 'Modal Pemilik',
            'kategori' => 'Ekuitas',
            'saldo_normal' => 'Kredit',
        ]);
        $this->createJournal($period, '2026-09-01', 'Modal awal', [
            [$cash, 5000, 0],
            [$capital, 0, 5000],
        ]);

        $result = app(FinancialHealthCheckService::class)->checkBalanceSheet($period->id);

        $this->assertTrue($result['is_healthy']);
        $this->assertSame('balance_sheet', $result['check']);
        $this->assertEqualsWithDelta(0, $result['difference'], 0.01);
    }

    private function createJournal(AccountingPeriod $period, string $date, string $description, array $lines): void
    {
        $journal = GeneralJournal::create([
            'tanggal' => $date,
            'no_bukti' => 'JU-' . $period->id . '-' . $date . '-' . $description,
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
