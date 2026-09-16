<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralJournal;
use App\Models\JournalItem;
use App\Models\MonthlySummary;
use App\Services\PeriodClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_period_runs_health_checks_and_generates_monthly_summary(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'opening_cash_balance' => 0,
            'closing_inventory_value' => 0,
            'status' => 'open',
        ]);
        $cash = $this->account('1010', 'Kas & Bank', 'Aset', 'Debit');
        $capital = $this->account('3101', 'Modal Pemilik', 'Ekuitas', 'Kredit');
        $this->journal($period, $cash, $capital);

        $summary = app(PeriodClosingService::class)->close($period);

        $this->assertSame('closed', $period->fresh()->status);
        $this->assertSame($period->id, $summary->accounting_period_id);
        $this->assertSame($period->id, MonthlySummary::first()->accounting_period_id);
    }

    public function test_closing_period_is_rejected_when_health_check_fails(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'closing_inventory_value' => 0,
            'status' => 'open',
        ]);
        $cash = $this->account('1010', 'Kas & Bank', 'Aset', 'Debit');
        $journal = GeneralJournal::create([
            'tanggal' => $period->start_date,
            'no_bukti' => 'CLOSE-TEST-002',
            'keterangan' => 'Jurnal tidak seimbang',
            'source_type' => 'MANUAL',
        ]);
        JournalItem::create(['journal_id' => $journal->id, 'coa_id' => $cash->id, 'kode_coa' => $cash->kode_akun, 'debit' => 1000, 'kredit' => 0]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tutup periode ditolak');
        app(PeriodClosingService::class)->close($period);
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

    private function journal(AccountingPeriod $period, ChartOfAccount $cash, ChartOfAccount $capital): void
    {
        $journal = GeneralJournal::create([
            'tanggal' => $period->start_date,
            'no_bukti' => 'CLOSE-TEST-001',
            'keterangan' => 'Modal awal',
            'source_type' => 'MANUAL',
        ]);
        JournalItem::create(['journal_id' => $journal->id, 'coa_id' => $cash->id, 'kode_coa' => $cash->kode_akun, 'debit' => 1000, 'kredit' => 0]);
        JournalItem::create(['journal_id' => $journal->id, 'coa_id' => $capital->id, 'kode_coa' => $capital->kode_akun, 'debit' => 0, 'kredit' => 1000]);
    }
}
