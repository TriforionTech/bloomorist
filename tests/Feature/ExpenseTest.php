<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\GeneralJournal;
use App\Models\User;
use App\Services\ExpenseService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_can_create_and_edit_draft_expense(): void
    {
        $coaBeban = ChartOfAccount::where('kode_akun', '6101')->first();
        $coaKas = ChartOfAccount::where('kode_akun', '1101')->first();

        $expense = Expense::create([
            'keterangan' => 'Pembelian Bunga Segar',
            'nominal' => 150000,
            'coa_id' => $coaBeban->id,
            'coa_kredit_id' => $coaKas->id,
            'status' => 'draft',
        ]);

        $this->assertTrue($expense->isDraft());
        $this->assertTrue($expense->isEditable());
        $this->assertTrue($expense->isDeletable());

        // Edit while in draft
        $expense->update(['nominal' => 200000]);
        $this->assertEquals(200000, $expense->fresh()->nominal);
    }

    public function test_can_post_draft_expense_and_creates_journal(): void
    {
        $coaBeban = ChartOfAccount::where('kode_akun', '6101')->first();
        $coaKas = ChartOfAccount::where('kode_akun', '1101')->first();

        $expense = Expense::create([
            'keterangan' => 'Biaya Operasional Toko',
            'nominal' => 75000,
            'coa_id' => $coaBeban->id,
            'coa_kredit_id' => $coaKas->id,
            'status' => 'draft',
        ]);

        app(ExpenseService::class)->postExpense($expense);

        $this->assertEquals('posted', $expense->fresh()->status);
        $this->assertTrue($expense->fresh()->isPosted());
        $this->assertFalse($expense->fresh()->isEditable());
        $this->assertFalse($expense->fresh()->isDeletable());

        // Assert General Journal created
        $journal = GeneralJournal::where('reference_id', $expense->id)
            ->where('source_type', 'EXPENSE')
            ->first();

        $this->assertNotNull($journal);
        $this->assertEquals('Biaya Operasional Toko', $journal->keterangan);
        $this->assertEquals(2, $journal->items()->count());

        $debitItem = $journal->items()->where('coa_id', $coaBeban->id)->first();
        $this->assertNotNull($debitItem);
        $this->assertEquals(75000, $debitItem->debit);
        $this->assertEquals(0, $debitItem->kredit);

        $kreditItem = $journal->items()->where('coa_id', $coaKas->id)->first();
        $this->assertNotNull($kreditItem);
        $this->assertEquals(0, $kreditItem->debit);
        $this->assertEquals(75000, $kreditItem->kredit);
    }

    public function test_posted_expense_cannot_be_edited_or_deleted_directly(): void
    {
        $coaBeban = ChartOfAccount::where('kode_akun', '6101')->first();
        $coaKas = ChartOfAccount::where('kode_akun', '1101')->first();

        $expense = Expense::create([
            'keterangan' => 'Sewa Gudang',
            'nominal' => 500000,
            'coa_id' => $coaBeban->id,
            'coa_kredit_id' => $coaKas->id,
            'status' => 'draft',
        ]);

        app(ExpenseService::class)->postExpense($expense);
        $postedExpense = $expense->fresh();

        // Expect Exception when attempting to update nominal
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak dapat diedit karena statusnya sudah POSTED');
        $postedExpense->update(['nominal' => 600000]);
    }

    public function test_can_void_posted_expense_and_creates_reversal_journal(): void
    {
        $coaBeban = ChartOfAccount::where('kode_akun', '6101')->first();
        $coaKas = ChartOfAccount::where('kode_akun', '1101')->first();

        $expense = Expense::create([
            'keterangan' => 'Beban Listrik Salah Input',
            'nominal' => 120000,
            'coa_id' => $coaBeban->id,
            'coa_kredit_id' => $coaKas->id,
            'status' => 'draft',
        ]);

        $service = app(ExpenseService::class);
        $service->postExpense($expense);

        // Now void the expense
        $service->voidExpense($expense->fresh());

        $this->assertEquals('void', $expense->fresh()->status);
        $this->assertTrue($expense->fresh()->isVoid());

        // Check reversal journal
        $reversal = GeneralJournal::where('reference_id', $expense->id)
            ->where('source_type', 'EXPENSE')
            ->where('keterangan', 'like', 'Pembatalan Expense:%')
            ->first();

        $this->assertNotNull($reversal);
        $this->assertEquals('Pembatalan Expense: Beban Listrik Salah Input', $reversal->keterangan);

        // Reversal debit should be Kas/Bank (reverse)
        $revDebit = $reversal->items()->where('coa_id', $coaKas->id)->first();
        $this->assertEquals(120000, $revDebit->debit);
        $this->assertEquals(0, $revDebit->kredit);

        // Reversal kredit should be Beban (reverse)
        $revKredit = $reversal->items()->where('coa_id', $coaBeban->id)->first();
        $this->assertEquals(0, $revKredit->debit);
        $this->assertEquals(120000, $revKredit->kredit);
    }

    public function test_cannot_post_expense_when_accounting_period_is_closed(): void
    {
        $coaBeban = ChartOfAccount::where('kode_akun', '6101')->first();
        $coaKas = ChartOfAccount::where('kode_akun', '1101')->first();

        // Close all accounting periods
        \App\Models\AccountingPeriod::query()->update(['status' => 'closed']);

        $expense = Expense::create([
            'keterangan' => 'Pengeluaran di Periode Ditutup',
            'nominal' => 250000,
            'coa_id' => $coaBeban->id,
            'coa_kredit_id' => $coaKas->id,
            'status' => 'draft',
        ]);

        $this->expectException(\App\Exceptions\AccountingPeriodClosedException::class);
        app(ExpenseService::class)->postExpense($expense);
    }

    public function test_cannot_void_expense_when_accounting_period_is_closed(): void
    {
        $coaBeban = ChartOfAccount::where('kode_akun', '6101')->first();
        $coaKas = ChartOfAccount::where('kode_akun', '1101')->first();

        // Ensure current period is open first to allow initial posting
        \App\Models\AccountingPeriod::query()->update(['status' => 'open']);

        $expense = Expense::create([
            'keterangan' => 'Pengeluaran Sebelum Tutup Buku',
            'nominal' => 100000,
            'coa_id' => $coaBeban->id,
            'coa_kredit_id' => $coaKas->id,
            'status' => 'draft',
        ]);

        $service = app(ExpenseService::class);
        $service->postExpense($expense);

        // Now close all accounting periods
        \App\Models\AccountingPeriod::query()->update(['status' => 'closed']);

        $this->expectException(\App\Exceptions\AccountingPeriodClosedException::class);
        $service->voidExpense($expense->fresh());
    }

    public function test_filament_list_expenses_renders_with_tabs_and_actions(): void
    {
        $user = User::create([
            'name' => 'Admin Expense',
            'email' => 'admin_expense@example.com',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);

        $coaBeban = ChartOfAccount::where('kode_akun', '6101')->first();
        $coaKas = ChartOfAccount::where('kode_akun', '1101')->first();

        $expense = Expense::create([
            'keterangan' => 'Beban Perawatan Display Toko',
            'nominal' => 50000,
            'coa_id' => $coaBeban->id,
            'coa_kredit_id' => $coaKas->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user);

        \Livewire\Livewire::test(\App\Filament\Resources\Expenses\Pages\ListExpenses::class)
            ->assertStatus(200)
            ->assertSee('Beban Perawatan Display Toko')
            ->assertSee('Rp 50.000')
            ->assertTableActionExists('postExpense')
            ->assertTableActionExists('voidExpense')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('delete');
    }

    public function test_filament_can_post_and_void_expense_via_table_actions(): void
    {
        $user = User::create([
            'name' => 'Admin Expense 2',
            'email' => 'admin_expense2@example.com',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);

        $coaBeban = ChartOfAccount::where('kode_akun', '6101')->first();
        $coaKas = ChartOfAccount::where('kode_akun', '1101')->first();

        $expense = Expense::create([
            'keterangan' => 'Beban Kebersihan',
            'nominal' => 30000,
            'coa_id' => $coaBeban->id,
            'coa_kredit_id' => $coaKas->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user);

        // 1. Post expense via table action
        \Livewire\Livewire::test(\App\Filament\Resources\Expenses\Pages\ListExpenses::class)
            ->callTableAction('postExpense', $expense)
            ->assertHasNoTableActionErrors();

        $this->assertEquals('posted', $expense->fresh()->status);
        $this->assertDatabaseHas('bl_general_journals_t', [
            'reference_id' => $expense->id,
            'source_type' => 'EXPENSE',
        ]);

        // 2. Void expense via table action
        \Livewire\Livewire::test(\App\Filament\Resources\Expenses\Pages\ListExpenses::class)
            ->callTableAction('voidExpense', $expense->fresh())
            ->assertHasNoTableActionErrors();

        $this->assertEquals('void', $expense->fresh()->status);
        $this->assertDatabaseHas('bl_general_journals_t', [
            'reference_id' => $expense->id,
            'source_type' => 'EXPENSE',
            'keterangan' => 'Pembatalan Expense: Beban Kebersihan',
        ]);
    }

    public function test_filament_edit_expense_blocks_posted_record(): void
    {
        $user = User::create([
            'name' => 'Admin Expense 3',
            'email' => 'admin_expense3@example.com',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);

        $coaBeban = ChartOfAccount::where('kode_akun', '6101')->first();
        $coaKas = ChartOfAccount::where('kode_akun', '1101')->first();

        $expense = Expense::create([
            'keterangan' => 'Beban Keamanan',
            'nominal' => 40000,
            'coa_id' => $coaBeban->id,
            'coa_kredit_id' => $coaKas->id,
            'status' => 'draft',
        ]);

        app(ExpenseService::class)->postExpense($expense);

        $this->actingAs($user);

        // Attempting to access edit page of a posted expense must be forbidden by policy
        \Livewire\Livewire::test(\App\Filament\Resources\Expenses\Pages\EditExpense::class, [
            'record' => $expense->id,
        ])->assertForbidden();
    }

    public function test_filament_create_expense_page_uses_full_width_layout(): void
    {
        $user = User::create([
            'name' => 'Admin Expense Full Width',
            'email' => 'admin_expense_full@example.com',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);

        $this->actingAs($user);

        $response = $this->get(\App\Filament\Resources\Expenses\ExpenseResource::getUrl('create'));
        $response->assertSuccessful();
        $response->assertSee('fi-width-full', false);
        $response->assertSee('Informasi Pengeluaran');
    }
}
