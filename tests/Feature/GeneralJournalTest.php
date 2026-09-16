<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralJournal;
use App\Models\AccountingAudit;
use App\Services\JournalEntryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GeneralJournalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create an open period
        AccountingPeriod::create([
            'label' => 'Bulan Ini',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);
        
        // Create some COAs
        ChartOfAccount::create([
            'kode_akun' => '1102',
            'nama_akun' => 'Bank',
            'kategori' => 'Aset',
            'saldo_normal' => 'Debit'
        ]);
        ChartOfAccount::create([
            'kode_akun' => '3101',
            'nama_akun' => 'Modal Pemilik',
            'kategori' => 'Ekuitas',
            'saldo_normal' => 'Kredit'
        ]);
    }

    public function test_journal_entry_service_rejects_unbalanced_entry()
    {
        $service = app(JournalEntryService::class);
        $coaBank = ChartOfAccount::where('kode_akun', '1102')->first();
        $coaModal = ChartOfAccount::where('kode_akun', '3101')->first();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Jurnal tidak seimbang. Debit: 50000, Kredit: 40000');

        $service->createManualEntry(now()->toDateString(), 'Test Unbalanced', [
            ['coa_id' => $coaBank->id, 'debit' => 50000, 'kredit' => 0],
            ['coa_id' => $coaModal->id, 'debit' => 0, 'kredit' => 40000],
        ]);
    }

    public function test_journal_entry_service_accepts_balanced_entry()
    {
        $service = app(JournalEntryService::class);
        $coaBank = ChartOfAccount::where('kode_akun', '1102')->first();
        $coaModal = ChartOfAccount::where('kode_akun', '3101')->first();

        $journal = $service->createManualEntry(now()->toDateString(), 'Modal Awal', [
            ['coa_id' => $coaBank->id, 'debit' => 59266000, 'kredit' => 0],
            ['coa_id' => $coaModal->id, 'debit' => 0, 'kredit' => 59266000],
        ]);

        $this->assertNotNull($journal->id);
        $this->assertEquals(2, $journal->items()->count());
        $this->assertDatabaseHas('bl_accounting_audits_t', [
            'journal_id' => $journal->id,
            'action' => 'created',
        ]);
    }

    public function test_general_journal_model_rejects_closed_period()
    {
        // Create a closed period for last month
        AccountingPeriod::create([
            'label' => 'Bulan Lalu',
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
            'status' => 'closed',
            'closing_inventory_value' => 0,
            'closed_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah ditutup. Jurnal tidak bisa disimpan.');

        GeneralJournal::create([
            'tanggal' => now()->subMonth()->startOfMonth()->toDateString(),
            'no_bukti' => 'JU-CLOSED',
            'keterangan' => 'Test',
            'source_type' => 'MANUAL',
        ]);
    }
}
