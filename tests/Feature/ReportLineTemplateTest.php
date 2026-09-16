<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralJournal;
use App\Models\JournalItem;
use App\Models\ReportLineTemplate;
use App\Services\ReportLineTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportLineTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_template_engine_resolves_account_patterns_and_subtotals(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);
        $sales = $this->account('4101', 'Penjualan', 'Pendapatan', 'Kredit');
        $returns = $this->account('4102', 'Retur', 'Pendapatan', 'Debit');
        $expense = $this->account('6101', 'Gaji', 'Beban', 'Debit');
        $cash = $this->account('1010', 'Kas', 'Aset', 'Debit');
        $this->journal($period, [[$cash, 1000, 0], [$sales, 0, 1000]]);
        $this->journal($period, [[$returns, 100, 0], [$cash, 0, 100]]);
        $this->journal($period, [[$expense, 200, 0], [$cash, 0, 200]]);

        ReportLineTemplate::create([
            'report_type' => 'income_statement',
            'line_key' => 'sales',
            'label' => 'Penjualan',
            'account_codes' => ['4101'],
            'sign' => 1,
            'sort_order' => 10,
        ]);
        ReportLineTemplate::create([
            'report_type' => 'income_statement',
            'line_key' => 'returns',
            'label' => 'Retur',
            'account_codes' => ['4102'],
            'sign' => -1,
            'sort_order' => 20,
        ]);
        ReportLineTemplate::create([
            'report_type' => 'income_statement',
            'line_key' => 'net_sales',
            'label' => 'Penjualan Bersih',
            'is_subtotal' => true,
            'subtotal_formula' => 'sales + returns',
            'sort_order' => 30,
        ]);
        ReportLineTemplate::create([
            'report_type' => 'income_statement',
            'line_key' => 'expenses',
            'label' => 'Beban',
            'account_codes' => ['6*'],
            'sign' => 1,
            'sort_order' => 40,
        ]);

        $result = app(ReportLineTemplateService::class)->generate(
            'income_statement',
            $period->start_date->startOfDay(),
            $period->end_date->endOfDay(),
        );

        $this->assertSame(1000.0, $result['sales']);
        $this->assertSame(-100.0, $result['returns']);
        $this->assertSame(900.0, $result['net_sales']);
        $this->assertSame(200.0, $result['expenses']);
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

    private function journal(AccountingPeriod $period, array $lines): void
    {
        $journal = GeneralJournal::create([
            'tanggal' => $period->start_date,
            'no_bukti' => 'TPL-' . uniqid(),
            'keterangan' => 'Template test',
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
