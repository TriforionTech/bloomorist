<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralJournal;
use App\Models\JournalItem;
use App\Services\AccountingService;
use App\Services\CashFlowService;
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

    /**
     * Whitebox: Validates all new fields added to getIncomeStatement return array.
     * Checks penjualan_bersih, pembelian_bersih, barang_tersedia_dijual, hpp,
     * laba_kotor, beban_operasional, laba_usaha, pendapatan_luar_usaha, and balances_by_code.
     */
    public function test_income_statement_returns_all_new_fields(): void
    {
        $priorPeriod = AccountingPeriod::create([
            'label' => 'Juli 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'open',
            'closing_inventory_value' => 2000,
        ]);
        $period = AccountingPeriod::create([
            'label' => 'Agustus 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'open',
            'closing_inventory_value' => 500,
        ]);

        $accounts = [];
        foreach ([
            ['1101', 'Kas',          'Aset',      'Debit'],
            ['1104', 'Persediaan',   'Aset',      'Debit'],
            ['4101', 'Penjualan',    'Pendapatan', 'Kredit'],
            ['4102', 'Retur Jual',   'Pendapatan', 'Debit'],
            ['4103', 'Pend Bunga',   'Pendapatan', 'Kredit'],
            ['4104', 'Pend Lain',    'Pendapatan', 'Kredit'],
            ['5101', 'Pembelian',    'Beban',     'Debit'],
            ['5102', 'Retur Beli',   'Beban',     'Kredit'],
            ['5103', 'Angkut Beli',  'Beban',     'Debit'],
            ['6101', 'Beban Gaji',   'Beban',     'Debit'],
        ] as [$code, $name, $cat, $norm]) {
            $accounts[$code] = ChartOfAccount::create([
                'kode_akun' => $code, 'nama_akun' => $name,
                'kategori'  => $cat,  'saldo_normal' => $norm,
            ]);
        }

        // Prior period: opening inventory of 2000 — must be BEFORE closing period
        $this->createJournal($priorPeriod, '2026-07-01', 'Persediaan awal', [
            [$accounts['1104'], 2000, 0],
            [$accounts['1101'], 0, 2000],
        ]);
        $priorPeriod->update(['status' => 'closed', 'closed_at' => now()]);
        // Sales 10,000, Retur 1,000 => net 9,000
        $this->createJournal($period, '2026-08-05', 'Penjualan', [
            [$accounts['1101'], 10000, 0],
            [$accounts['4101'], 0, 10000],
        ]);
        $this->createJournal($period, '2026-08-06', 'Retur Penjualan', [
            [$accounts['4102'], 1000, 0],
            [$accounts['1101'], 0, 1000],
        ]);
        // Purchases 4,000, Retur 500 => net 3,500. Angkut 200
        $this->createJournal($period, '2026-08-07', 'Pembelian', [
            [$accounts['5101'], 4000, 0],
            [$accounts['1101'], 0, 4000],
        ]);
        $this->createJournal($period, '2026-08-08', 'Retur Pembelian', [
            [$accounts['1101'], 500, 0],
            [$accounts['5102'], 0, 500],
        ]);
        $this->createJournal($period, '2026-08-08', 'Angkut Pembelian', [
            [$accounts['5103'], 200, 0],
            [$accounts['1101'], 0, 200],
        ]);
        // Operating expense: Gaji 800
        $this->createJournal($period, '2026-08-10', 'Beban Gaji', [
            [$accounts['6101'], 800, 0],
            [$accounts['1101'], 0, 800],
        ]);
        // Other income: Bunga 100, Lain 50
        $this->createJournal($period, '2026-08-15', 'Pendapatan Bunga', [
            [$accounts['1101'], 100, 0],
            [$accounts['4103'], 0, 100],
        ]);
        $this->createJournal($period, '2026-08-16', 'Pendapatan Lain', [
            [$accounts['1101'], 50, 0],
            [$accounts['4104'], 0, 50],
        ]);

        $report = app(AccountingService::class)->getIncomeStatement(
            $period->start_date->startOfDay(),
            $period->end_date->endOfDay(),
        );

        // penjualan_bersih = 10000 - 1000 = 9000
        $this->assertSame(9000.0, $report['penjualan_bersih'], 'penjualan_bersih');

        // persediaan_awal = prior-period inventory closing balance = 2000
        $this->assertSame(2000.0, $report['persediaan_awal'], 'persediaan_awal');

        // pembelian_bersih = 4000 - 500 = 3500
        $this->assertSame(3500.0, $report['pembelian_bersih'], 'pembelian_bersih');

        // barang_tersedia_dijual = persediaan_awal + pembelian_bersih + angkut = 2000+3500+200 = 5700
        $this->assertSame(5700.0, $report['barang_tersedia_dijual'], 'barang_tersedia_dijual');

        // persediaan_akhir = 500 (from closing_inventory_value)
        $this->assertSame(500.0, (float) $report['persediaan_akhir'], 'persediaan_akhir');

        // hpp = 5700 - 500 = 5200
        $this->assertSame(5200.0, $report['hpp'], 'hpp');

        // laba_kotor = penjualan_bersih - hpp = 9000 - 5200 = 3800
        $this->assertSame(3800.0, $report['laba_kotor'], 'laba_kotor');

        // beban_operasional = 800 (gaji only; 5101/5102/5103 excluded)
        $this->assertSame(800.0, $report['beban_operasional'], 'beban_operasional');

        // laba_usaha = 3800 - 800 = 3000
        $this->assertSame(3000.0, $report['laba_usaha'], 'laba_usaha');

        // pendapatan_luar_usaha = 100 + 50 = 150
        $this->assertSame(150.0, $report['pendapatan_luar_usaha'], 'pendapatan_luar_usaha');

        // laba_rugi = 3000 + 150 = 3150
        $this->assertSame(3150.0, $report['laba_rugi'], 'laba_rugi');

        // balances_by_code must contain mapped values for key COA codes
        $this->assertArrayHasKey('4101', $report['balances_by_code'], 'balances_by_code has 4101');
        $this->assertArrayHasKey('6101', $report['balances_by_code'], 'balances_by_code has 6101');
        $this->assertEqualsWithDelta(10000, $report['balances_by_code']['4101'], 0.01, '4101 in balances_by_code');
    }

    /**
     * Whitebox: Validates that balances_by_code in getBalanceSheet is keyed by COA code.
     */
    public function test_balance_sheet_returns_balances_by_code(): void
    {
        $period = AccountingPeriod::create([
            'label'      => 'Agustus 2026',
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-31',
            'status'     => 'open',
            'closing_inventory_value' => 0,
        ]);

        $kas     = ChartOfAccount::create(['kode_akun'=>'1101','nama_akun'=>'Kas',         'kategori'=>'Aset',    'saldo_normal'=>'Debit']);
        $hutang  = ChartOfAccount::create(['kode_akun'=>'2101','nama_akun'=>'Hutang',      'kategori'=>'Kewajiban','saldo_normal'=>'Kredit']);
        $modal   = ChartOfAccount::create(['kode_akun'=>'3101','nama_akun'=>'Modal',       'kategori'=>'Ekuitas', 'saldo_normal'=>'Kredit']);

        $this->createJournal($period, '2026-08-01', 'Modal awal', [
            [$kas,   5000, 0],
            [$modal, 0, 5000],
        ]);
        $this->createJournal($period, '2026-08-05', 'Hutang', [
            [$kas,    1000, 0],
            [$hutang, 0,    1000],
        ]);

        $report = app(AccountingService::class)->getBalanceSheet($period->end_date);

        $this->assertArrayHasKey('balances_by_code', $report, 'has balances_by_code');
        $this->assertEqualsWithDelta(6000, $report['balances_by_code']['1101'], 0.01, '1101 Kas balance');
        $this->assertEqualsWithDelta(1000, $report['balances_by_code']['2101'], 0.01, '2101 Hutang balance');
        $this->assertEqualsWithDelta(5000, $report['balances_by_code']['3101'], 0.01, '3101 Modal balance');
    }

    /**
     * Whitebox: Total Aktiva must always equal Total Kewajiban + Total Ekuitas (is_balanced).
     */
    public function test_balance_sheet_is_balanced_with_cogs(): void
    {
        $priorPeriod = AccountingPeriod::create([
            'label' => 'Juli 2026', 'start_date' => '2026-07-01',
            'end_date' => '2026-07-31', 'status' => 'open', 'closing_inventory_value' => 3000,
        ]);
        $period = AccountingPeriod::create([
            'label' => 'Agustus 2026', 'start_date' => '2026-08-01',
            'end_date' => '2026-08-31', 'status' => 'open', 'closing_inventory_value' => 1500,
        ]);

        $kas     = ChartOfAccount::create(['kode_akun'=>'1101','nama_akun'=>'Kas',        'kategori'=>'Aset',    'saldo_normal'=>'Debit']);
        $inv     = ChartOfAccount::create(['kode_akun'=>'1104','nama_akun'=>'Persediaan', 'kategori'=>'Aset',    'saldo_normal'=>'Debit']);
        $modal   = ChartOfAccount::create(['kode_akun'=>'3101','nama_akun'=>'Modal',      'kategori'=>'Ekuitas', 'saldo_normal'=>'Kredit']);
        $sales   = ChartOfAccount::create(['kode_akun'=>'4101','nama_akun'=>'Penjualan',  'kategori'=>'Pendapatan','saldo_normal'=>'Kredit']);
        $purch   = ChartOfAccount::create(['kode_akun'=>'5101','nama_akun'=>'Pembelian',  'kategori'=>'Beban',   'saldo_normal'=>'Debit']);
        ChartOfAccount::create(['kode_akun'=>'4102','nama_akun'=>'Retur Jual','kategori'=>'Pendapatan','saldo_normal'=>'Debit']);
        ChartOfAccount::create(['kode_akun'=>'5102','nama_akun'=>'Retur Beli','kategori'=>'Beban','saldo_normal'=>'Kredit']);
        ChartOfAccount::create(['kode_akun'=>'5103','nama_akun'=>'Angkut Beli','kategori'=>'Beban','saldo_normal'=>'Debit']);

        // Opening inventory from prior period — create journal THEN close
        $this->createJournal($priorPeriod, '2026-07-01', 'Inv awal', [[$inv, 3000, 0], [$modal, 0, 3000]]);
        $priorPeriod->update(['status' => 'closed', 'closed_at' => now()]);

        // Modal setoran
        $this->createJournal($period, '2026-08-01', 'Modal', [[$kas, 20000, 0], [$modal, 0, 20000]]);
        // Sales
        $this->createJournal($period, '2026-08-10', 'Jual', [[$kas, 15000, 0], [$sales, 0, 15000]]);
        // Purchases
        $this->createJournal($period, '2026-08-12', 'Beli', [[$purch, 5000, 0], [$kas, 0, 5000]]);

        $report = app(AccountingService::class)->getBalanceSheet($period->end_date);

        $this->assertTrue($report['is_balanced'], sprintf(
            'Balance sheet not balanced. Aktiva=%s, Kewajiban+Ekuitas=%s, diff=%s',
            $report['total_aset'],
            $report['total_kewajiban_ekuitas'],
            $report['total_aset'] - $report['total_kewajiban_ekuitas'],
        ));
    }

    /**
     * Whitebox: laba_kotor = penjualan_bersih - hpp is always satisfied.
     */
    public function test_income_statement_gross_profit_arithmetic_is_correct(): void
    {
        $priorPeriod = AccountingPeriod::create([
            'label' => 'Juli 2026', 'start_date' => '2026-07-01',
            'end_date' => '2026-07-31', 'status' => 'open', 'closing_inventory_value' => 1200,
        ]);
        $period = AccountingPeriod::create([
            'label' => 'Agustus 2026', 'start_date' => '2026-08-01',
            'end_date' => '2026-08-31', 'status' => 'open', 'closing_inventory_value' => 700,
        ]);

        $accounts = [];
        foreach ([
            ['1101','Kas','Aset','Debit'],
            ['1104','Persediaan','Aset','Debit'],
            ['4101','Penjualan','Pendapatan','Kredit'],
            ['4102','Retur Jual','Pendapatan','Debit'],
            ['5101','Pembelian','Beban','Debit'],
            ['5102','Retur Beli','Beban','Kredit'],
            ['5103','Angkut Beli','Beban','Debit'],
        ] as [$c,$n,$k,$s]) {
            $accounts[$c] = ChartOfAccount::create(['kode_akun'=>$c,'nama_akun'=>$n,'kategori'=>$k,'saldo_normal'=>$s]);
        }

        $this->createJournal($priorPeriod, '2026-07-01', 'Inv awal', [[$accounts['1104'], 1200, 0], [$accounts['1101'], 0, 1200]]);
        $priorPeriod->update(['status' => 'closed', 'closed_at' => now()]);
        $this->createJournal($period, '2026-08-05', 'Jual',       [[$accounts['1101'], 8000, 0], [$accounts['4101'], 0, 8000]]);
        $this->createJournal($period, '2026-08-06', 'Retur Jual', [[$accounts['4102'], 300, 0],  [$accounts['1101'], 0, 300]]);
        $this->createJournal($period, '2026-08-07', 'Beli',       [[$accounts['5101'], 3500, 0], [$accounts['1101'], 0, 3500]]);
        $this->createJournal($period, '2026-08-08', 'Retur Beli', [[$accounts['1101'], 200, 0],  [$accounts['5102'], 0, 200]]);
        $this->createJournal($period, '2026-08-09', 'Angkut',     [[$accounts['5103'], 150, 0],  [$accounts['1101'], 0, 150]]);

        $report = app(AccountingService::class)->getIncomeStatement(
            $period->start_date->startOfDay(), $period->end_date->endOfDay(),
        );

        // Verify arithmetic identity: laba_kotor = penjualan_bersih - hpp
        $this->assertEqualsWithDelta(
            $report['penjualan_bersih'] - $report['hpp'],
            $report['laba_kotor'],
            0.01,
            'Laba Kotor = Penjualan Bersih - HPP'
        );

        // Verify arithmetic identity: laba_rugi = laba_usaha + pendapatan_luar_usaha
        $this->assertEqualsWithDelta(
            $report['laba_usaha'] + $report['pendapatan_luar_usaha'],
            $report['laba_rugi'],
            0.01,
            'Laba Rugi = Laba Usaha + Pendapatan Luar Usaha'
        );
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
