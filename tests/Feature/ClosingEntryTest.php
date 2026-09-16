<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralJournal;
use App\Models\JournalItem;
use App\Services\ClosingEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosingEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_entry_closes_nominal_accounts_to_owner_capital(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);
        $sales = $this->account('4101', 'Penjualan', 'Pendapatan', 'Kredit');
        $expense = $this->account('6101', 'Beban Gaji', 'Beban', 'Debit');
        $capital = $this->account('3101', 'Modal Pemilik', 'Ekuitas', 'Kredit');
        $this->journal($period, [[$sales, 0, 1000], [$expense, 200, 0]]);

        $closing = app(ClosingEntryService::class)->post($period);

        $this->assertSame('CLOSING', $closing->source_type);
        $this->assertSame(800, $closing->items()->where('coa_id', $capital->id)->sum('kredit'));
        $this->assertSame(1000, $closing->items()->where('coa_id', $sales->id)->sum('debit'));
        $this->assertSame(200, $closing->items()->where('coa_id', $expense->id)->sum('kredit'));
        $this->assertDatabaseHas('bl_accounting_audits_t', [
            'journal_id' => $closing->id,
            'action' => 'created',
        ]);
    }

    public function test_closing_entry_cannot_be_posted_twice(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);
        $sales = $this->account('4101', 'Penjualan', 'Pendapatan', 'Kredit');
        $capital = $this->account('3101', 'Modal Pemilik', 'Ekuitas', 'Kredit');
        $this->journal($period, [[$sales, 0, 1000], [$capital, 1000, 0]]);
        $service = app(ClosingEntryService::class);
        $service->post($period);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah diposting');
        $service->post($period);
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
            'no_bukti' => 'CLOSE-' . uniqid(),
            'keterangan' => 'Transaksi periode',
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
