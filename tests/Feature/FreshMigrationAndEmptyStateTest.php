<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\CashFlowService;
use App\Services\ExpenseService;
use App\Services\PeriodClosingService;
use App\Services\TrialBalanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreshMigrationAndEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_master_foundations(): void
    {
        $this->seed(DatabaseSeeder::class);

        // 1. Admin User
        $this->assertDatabaseHas('users', [
            'email' => 'bloomorist@gmail.com',
            'is_super_admin' => 1,
        ]);

        // 2. COA (all essential accounts)
        $this->assertTrue(ChartOfAccount::where('kode_akun', '1101')->exists(), 'COA 1101 (Kas) must exist');
        $this->assertTrue(ChartOfAccount::where('kode_akun', '1104')->exists(), 'COA 1104 (Persediaan Bunga) must exist');
        $this->assertTrue(ChartOfAccount::where('kode_akun', '4101')->exists(), 'COA 4101 (Penjualan) must exist');
        $this->assertTrue(ChartOfAccount::where('kode_akun', '5101')->exists(), 'COA 5101 (Pembelian Bunga) must exist');
        $this->assertTrue(ChartOfAccount::where('kode_akun', '6101')->exists(), 'COA 6101 (Beban Gaji) must exist');
        $this->assertTrue(ChartOfAccount::where('kode_akun', '6106')->exists(), 'COA 6106 (Beban Rusak/Layu) must exist');

        // 3. Open Accounting Period for current month
        $period = AccountingPeriod::where('status', 'open')->first();
        $this->assertNotNull($period, 'An open AccountingPeriod must be seeded');
        $this->assertEquals(now()->format('F Y'), $period->label);
    }

    public function test_financial_reports_handle_zero_transactions_gracefully(): void
    {
        $this->seed(DatabaseSeeder::class);
        $period = AccountingPeriod::where('status', 'open')->first();

        // 1. Income Statement on empty DB
        $accounting = app(AccountingService::class);
        $income = $accounting->getIncomeStatement($period->start_date->startOfDay(), $period->end_date->endOfDay());
        $this->assertEquals(0, $income['penjualan_bersih']);
        $this->assertEquals(0, $income['hpp']);
        $this->assertEquals(0, $income['laba_rugi']);

        // 2. Balance Sheet on empty DB
        $balance = $accounting->getBalanceSheet($period->end_date->copy()->endOfDay());
        $this->assertEquals(0, $balance['total_aset']);
        $this->assertEquals(0, $balance['total_kewajiban_ekuitas']);
        $this->assertTrue($balance['is_balanced']);

        // 3. Cash Flow on empty DB
        $cashFlow = app(CashFlowService::class)->getForPeriod($period->id);
        $this->assertEquals(0, $cashFlow['ending_cash']);
        $this->assertTrue($cashFlow['is_reconciled']);

        // 4. Trial Balance on empty DB
        $trialBalance = app(TrialBalanceService::class)->getForPeriod($period->id);
        $this->assertEquals(0, $trialBalance['total_debit']);
        $this->assertEquals(0, $trialBalance['total_credit']);
        $this->assertTrue($trialBalance['is_balanced']);
    }

    public function test_creating_and_posting_expense_on_fresh_db(): void
    {
        $this->seed(DatabaseSeeder::class);

        $coaBeban = ChartOfAccount::where('kode_akun', '6101')->first();
        $coaKas = ChartOfAccount::where('kode_akun', '1101')->first();

        $expense = Expense::create([
            'keterangan' => 'Beli Bensin',
            'nominal' => 50000,
            'coa_id' => $coaBeban->id,
            'coa_kredit_id' => $coaKas->id,
            'status' => 'draft',
        ]);

        $this->assertNotNull($expense->id);

        app(ExpenseService::class)->postExpense($expense);

        $this->assertEquals('posted', $expense->fresh()->status);
        $this->assertDatabaseHas('bl_general_journals_t', [
            'reference_id' => $expense->id,
            'source_type' => 'EXPENSE',
        ]);
    }

    public function test_invoice_payment_creates_journal_on_fresh_db(): void
    {
        $this->seed(DatabaseSeeder::class);

        $customer = Customer::create([
            'nama' => 'Test Customer',
            'alamat' => 'Jl. Mawar No. 1',
            'tipe_customer' => 'toko',
        ]);

        $product = Product::where('nama', 'Box')->first();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'customer_id' => $customer->id,
            'subtotal' => 35000,
            'grand_total' => 35000,
            'status' => 'pending',
            'issued_date' => now()->toDateString(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product?->id,
            'snapshot_sku' => 'PKG-BOX',
            'snapshot_name' => 'Box',
            'quantity' => 1,
            'unit_price' => 35000,
            'normal_price' => 35000,
        ]);

        $product->update(['stok' => 5]);

        Invoice::handleStatusChange($invoice, 'paid');

        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertDatabaseHas('bl_general_journals_t', [
            'reference_id' => $invoice->id,
            'source_type' => 'INVOICE',
        ]);
    }

    public function test_stock_opname_posting_on_fresh_db(): void
    {
        $this->seed(DatabaseSeeder::class);

        $product = Product::first();
        $initialStock = $product->stok;

        $opname = StockOpname::create([
            'reference_number' => 'SO-TEST-001',
            'date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_qty' => $initialStock,
            'physical_qty' => $initialStock + 5,
            'variance' => 5,
            'unit_cost' => $product->harga_beli,
            'total_value' => 5 * $product->harga_beli,
        ]);

        app(AccountingService::class)->createStockOpnameJournal($opname);

        $this->assertEquals('posted', $opname->fresh()->status);
        $this->assertEquals($initialStock + 5, $product->fresh()->stok);
    }

    public function test_period_closing_succeeds_on_fresh_db(): void
    {
        $this->seed(DatabaseSeeder::class);

        $period = AccountingPeriod::where('status', 'open')->first();

        $summary = app(PeriodClosingService::class)->close($period);

        $this->assertEquals('closed', $period->fresh()->status);
        $this->assertNotNull($summary->id);
    }
}
