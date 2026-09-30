<?php

namespace Tests\Feature;

use App\Filament\Resources\AccountingAudits\Pages\ListAccountingAudits;
use App\Models\AccountingAudit;
use App\Models\AccountingPeriod;
use App\Models\GeneralJournal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Admin Akuntansi',
            'email' => 'admin_akuntansi@example.com',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);

        $this->actingAs($this->user);

        AccountingPeriod::create([
            'label' => 'September 2026',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);
    }

    public function test_journal_creation_automatically_records_audit_trail(): void
    {
        $journal = GeneralJournal::create([
            'tanggal' => '2026-09-10',
            'no_bukti' => 'MEMO-001',
            'keterangan' => 'Jurnal uji pembuatan',
            'source_type' => 'MANUAL',
        ]);

        $audit = AccountingAudit::where('journal_id', $journal->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->user->id, $audit->user_id);
        $this->assertSame('MEMO-001', $audit->display_no_bukti);
        $this->assertSame('Jurnal uji pembuatan', $audit->display_keterangan);
    }

    public function test_journal_update_records_audit_trail(): void
    {
        $journal = GeneralJournal::create([
            'tanggal' => '2026-09-10',
            'no_bukti' => 'MEMO-002',
            'keterangan' => 'Keterangan awal',
            'source_type' => 'MANUAL',
        ]);

        $journal->update([
            'keterangan' => 'Keterangan setelah diubah',
        ]);

        $audit = AccountingAudit::where('journal_id', $journal->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('Keterangan setelah diubah', $audit->new_values['keterangan']);
    }

    public function test_journal_deletion_records_audit_trail_and_retains_no_bukti(): void
    {
        $journal = GeneralJournal::create([
            'tanggal' => '2026-09-10',
            'no_bukti' => 'MEMO-003',
            'keterangan' => 'Jurnal yang akan dihapus',
            'source_type' => 'MANUAL',
        ]);

        $journal->delete();

        $audit = AccountingAudit::where('action', 'deleted')->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertNull($audit->journal); // Relasi null karena journal sudah dihapus fisik
        $this->assertSame('MEMO-003', $audit->display_no_bukti); // Tetap terselamatkan dari old_values
        $this->assertSame('Jurnal yang akan dihapus', $audit->display_keterangan);
    }

    public function test_accounting_audit_trail_table_renders_successfully(): void
    {
        GeneralJournal::create([
            'tanggal' => '2026-09-10',
            'no_bukti' => 'AUDIT-TABLE-01',
            'keterangan' => 'Testing tabel audit',
            'source_type' => 'MANUAL',
        ]);

        Livewire::test(ListAccountingAudits::class)
            ->assertSuccessful()
            ->assertSee('AUDIT-TABLE-01')
            ->assertSee('CREATED');
    }
}
