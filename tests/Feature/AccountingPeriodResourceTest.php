<?php

namespace Tests\Feature;

use App\Filament\Resources\AccountingPeriods\Pages\CreateAccountingPeriod;
use App\Filament\Resources\AccountingPeriods\Pages\EditAccountingPeriod;
use App\Filament\Resources\AccountingPeriods\Pages\ListAccountingPeriods;
use App\Models\AccountingPeriod;
use App\Models\GeneralJournal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingPeriodResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Admin Akuntansi',
            'email' => 'admin_akuntansi@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->user);
    }

    public function test_can_list_accounting_periods(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'Agustus 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'open',
            'opening_cash_balance' => 0,
        ]);

        Livewire::test(ListAccountingPeriods::class)
            ->assertSuccessful()
            ->assertSee('Agustus 2026');
    }

    public function test_can_create_calendar_based_period_automatically(): void
    {
        Livewire::test(CreateAccountingPeriod::class)
            ->fillForm([
                'month' => '08',
                'year' => '2026',
                'opening_cash_balance' => 1000000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $period = AccountingPeriod::where('label', 'Agustus 2026')->first();
        $this->assertNotNull($period);
        $this->assertEquals('2026-08-01', $period->start_date->format('Y-m-d'));
        $this->assertEquals('2026-08-31', $period->end_date->format('Y-m-d'));
        $this->assertEquals('open', $period->status);
        $this->assertEquals(1000000, $period->opening_cash_balance);
    }

    public function test_prevents_duplicate_period_for_same_month_and_year(): void
    {
        AccountingPeriod::create([
            'label' => 'Agustus 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'open',
            'opening_cash_balance' => 0,
        ]);

        Livewire::test(CreateAccountingPeriod::class)
            ->fillForm([
                'month' => '08',
                'year' => '2026',
                'opening_cash_balance' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['month']);
    }

    public function test_can_edit_existing_period_and_hydrates_month_and_year(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'Oktober 2026',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'open',
            'opening_cash_balance' => 500000,
        ]);

        Livewire::test(EditAccountingPeriod::class, [
            'record' => $period->getRouteKey(),
        ])
            ->assertFormSet([
                'month' => '10',
                'year' => '2026',
                'label' => 'Oktober 2026',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ])
            ->fillForm([
                'opening_cash_balance' => 750000,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bl_accounting_periods_t', [
            'id' => $period->id,
            'opening_cash_balance' => 750000,
        ]);
    }

    public function test_general_journal_respects_calendar_period_boundary(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'Agustus 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        // Attempting to create a journal in August should throw since period is closed
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Periode akuntansi 'Agustus 2026' sudah ditutup");

        GeneralJournal::create([
            'tanggal' => '2026-08-15',
            'no_bukti' => 'JU-TEST-CLOSED',
            'keterangan' => 'Test jurnal di periode tutup',
            'source_type' => 'MANUAL',
        ]);
    }
}
