<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralJournal;
use App\Models\JournalItem;
use App\Services\TrialBalanceService;
use App\Services\FinancialHealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_balance_net_amount_is_shown_on_one_side(): void
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
            'nama_akun' => 'Modal',
            'kategori' => 'Ekuitas',
            'saldo_normal' => 'Kredit',
        ]);
        $journal = GeneralJournal::create([
            'tanggal' => '2026-09-10',
            'no_bukti' => 'JU-TEST-001',
            'keterangan' => 'Setoran modal',
            'source_type' => 'MANUAL',
        ]);
        JournalItem::create(['journal_id' => $journal->id, 'coa_id' => $cash->id, 'kode_coa' => '1101', 'debit' => 100000, 'kredit' => 0]);
        JournalItem::create(['journal_id' => $journal->id, 'coa_id' => $capital->id, 'kode_coa' => '3101', 'debit' => 0, 'kredit' => 100000]);

        $data = app(TrialBalanceService::class)->getForPeriod($period->id);

        $this->assertTrue($data['is_balanced']);
        $this->assertSame(100000, $data['total_debit']);
        $this->assertSame(100000, $data['total_credit']);
        $this->assertSame(100000, $data['rows']->firstWhere('account.id', $cash->id)['debit']);
        $this->assertSame(100000, $data['rows']->firstWhere('account.id', $capital->id)['credit']);
    }

    public function test_trial_balance_excludes_journals_outside_period(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);
        AccountingPeriod::create([
            'label' => 'Agustus 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'open',
        ]);
        $account = ChartOfAccount::create([
            'kode_akun' => '1101',
            'nama_akun' => 'Kas',
            'kategori' => 'Aset',
            'saldo_normal' => 'Debit',
        ]);
        $journal = GeneralJournal::create([
            'tanggal' => '2026-08-31',
            'no_bukti' => 'JU-TEST-002',
            'keterangan' => 'Di luar periode',
            'source_type' => 'MANUAL',
        ]);
        JournalItem::create(['journal_id' => $journal->id, 'coa_id' => $account->id, 'kode_coa' => '1101', 'debit' => 50000, 'kredit' => 0]);

        $data = app(TrialBalanceService::class)->getForPeriod($period->id);

        $this->assertSame(0, $data['total_debit']);
        $this->assertSame(0, $data['total_credit']);
    }

    public function test_financial_health_check_reports_trial_balance_status(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);

        $result = app(FinancialHealthCheckService::class)->checkTrialBalance($period->id);

        $this->assertTrue($result['is_healthy']);
        $this->assertSame('trial_balance', $result['check']);
        $this->assertSame(0, $result['difference']);
    }
}
