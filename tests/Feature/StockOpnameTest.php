<?php

namespace Tests\Feature;

use App\Filament\Resources\StockOpnames\Pages\CreateStockOpname;
use App\Filament\Resources\StockOpnames\Pages\EditStockOpname;
use App\Filament\Resources\StockOpnames\Pages\ListStockOpnames;
use App\Filament\Resources\StockOpnames\Pages\ViewStockOpname;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected AccountingPeriod $period;

    protected ChartOfAccount $coaPersediaan;

    protected ChartOfAccount $coaShrinkage;

    protected ChartOfAccount $coaGain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Admin Opname',
            'email' => 'admin_opname@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->coaPersediaan = ChartOfAccount::create([
            'kode_akun' => '1104',
            'nama_akun' => 'Persediaan Bunga',
            'saldo_normal' => 'Debit',
            'kategori' => 'Aktiva Lancar',
        ]);

        $this->coaShrinkage = ChartOfAccount::create([
            'kode_akun' => '6106',
            'nama_akun' => 'Beban Kerugian Bunga Rusak/Layu',
            'saldo_normal' => 'Debit',
            'kategori' => 'Beban Operasional',
        ]);

        $this->coaGain = ChartOfAccount::create([
            'kode_akun' => '4104',
            'nama_akun' => 'Pendapatan Lain-Lain',
            'saldo_normal' => 'Kredit',
            'kategori' => 'Pendapatan Lain-Lain',
        ]);

        $this->period = AccountingPeriod::create([
            'label' => 'September 2026',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);
    }

    public function test_stock_opname_list_page_can_be_rendered(): void
    {
        $this->actingAs($this->user);

        $opname = StockOpname::create([
            'reference_number' => 'SO-2026-09-001',
            'date' => now()->toDateString(),
            'status' => 'draft',
            'notes' => 'Pemeriksaan stok rutin',
        ]);

        Livewire::test(ListStockOpnames::class)
            ->assertSuccessful()
            ->assertSee('SO-2026-09-001')
            ->assertSee('draft');
    }

    public function test_stock_opname_create_page_can_be_rendered(): void
    {
        $this->actingAs($this->user);

        Livewire::test(CreateStockOpname::class)
            ->assertSuccessful()
            ->assertSee('Stock Opname Details')
            ->assertSee('Items');
    }

    public function test_stock_opname_can_be_created_as_draft_with_items(): void
    {
        $this->actingAs($this->user);

        $product1 = Product::create([
            'sku' => 'MW-01',
            'nama' => 'Mawar Merah',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 15000,
            'stok' => 100,
            'is_active' => true,
        ]);

        $product2 = Product::create([
            'sku' => 'MW-02',
            'nama' => 'Mawar Putih',
            'kategori' => 'bunga',
            'harga_beli' => 12000,
            'harga_jual' => 18000,
            'stok' => 50,
            'is_active' => true,
        ]);

        Livewire::test(CreateStockOpname::class)
            ->fillForm([
                'reference_number' => 'SO-2026-09-002',
                'date' => now()->toDateString(),
                'notes' => 'Draft Opname Bulanan',
                'items' => [
                    [
                        'product_id' => $product1->id,
                        'system_qty' => 100,
                        'physical_qty' => 98,
                        'variance' => -2,
                        'unit_cost' => 10000,
                        'total_value' => -20000,
                    ],
                    [
                        'product_id' => $product2->id,
                        'system_qty' => 50,
                        'physical_qty' => 53,
                        'variance' => 3,
                        'unit_cost' => 12000,
                        'total_value' => 36000,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bl_stock_opnames_t', [
            'reference_number' => 'SO-2026-09-002',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('bl_stock_opname_items_t', [
            'product_id' => $product1->id,
            'system_qty' => 100,
            'physical_qty' => 98,
            'variance' => -2,
            'unit_cost' => 10000,
            'total_value' => -20000,
        ]);

        $this->assertDatabaseHas('bl_stock_opname_items_t', [
            'product_id' => $product2->id,
            'system_qty' => 50,
            'physical_qty' => 53,
            'variance' => 3,
            'unit_cost' => 12000,
            'total_value' => 36000,
        ]);

        // Stock in master product must NOT change while still in draft
        $this->assertEquals(100, $product1->fresh()->stok);
        $this->assertEquals(50, $product2->fresh()->stok);
    }

    public function test_stock_opname_view_page_renders_details_and_items(): void
    {
        $this->actingAs($this->user);

        $product = Product::create([
            'sku' => 'MW-03',
            'nama' => 'Mawar Kuning',
            'kategori' => 'bunga',
            'harga_beli' => 8000,
            'harga_jual' => 14000,
            'stok' => 40,
            'is_active' => true,
        ]);

        $opname = StockOpname::create([
            'reference_number' => 'SO-VIEW-001',
            'date' => now()->toDateString(),
            'status' => 'draft',
            'notes' => 'Catatan View Test',
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_qty' => 40,
            'physical_qty' => 38,
            'variance' => -2,
            'unit_cost' => 8000,
            'total_value' => -16000,
        ]);

        Livewire::test(ViewStockOpname::class, ['record' => $opname->id])
            ->assertSuccessful()
            ->assertSee('SO-VIEW-001')
            ->assertSee('Catatan View Test')
            ->assertSee('Mawar Kuning')
            ->assertActionVisible('post')
            ->assertActionVisible('edit');
    }

    public function test_stock_opname_edit_page_can_be_rendered_and_updated_for_draft(): void
    {
        $this->actingAs($this->user);

        $opname = StockOpname::create([
            'reference_number' => 'SO-EDIT-001',
            'date' => now()->toDateString(),
            'status' => 'draft',
            'notes' => 'Catatan Lama',
        ]);

        Livewire::test(EditStockOpname::class, ['record' => $opname->id])
            ->assertSuccessful()
            ->fillForm([
                'notes' => 'Catatan Baru Diperbarui',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bl_stock_opnames_t', [
            'id' => $opname->id,
            'notes' => 'Catatan Baru Diperbarui',
        ]);
    }

    public function test_stock_opname_edit_page_is_forbidden_for_posted_record(): void
    {
        $this->actingAs($this->user);

        $opname = StockOpname::create([
            'reference_number' => 'SO-POSTED-001',
            'date' => now()->toDateString(),
            'status' => 'posted',
        ]);

        Livewire::test(EditStockOpname::class, ['record' => $opname->id])
            ->assertForbidden();
    }

    public function test_post_action_creates_shrinkage_loss_journal_and_adjusts_physical_stock(): void
    {
        $this->actingAs($this->user);

        $product = Product::create([
            'sku' => 'LOSS-01',
            'nama' => 'Mawar Merah Shrinkage',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 15000,
            'stok' => 100,
            'is_active' => true,
        ]);

        $opname = StockOpname::create([
            'reference_number' => 'SO-LOSS-001',
            'date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_qty' => 100,
            'physical_qty' => 98,
            'variance' => -2,
            'unit_cost' => 10000,
            'total_value' => -20000,
        ]);

        // Post from View page action
        Livewire::test(ViewStockOpname::class, ['record' => $opname->id])
            ->callAction('post')
            ->assertHasNoActionErrors();

        // 1. Stock updated in product master
        $this->assertEquals(98, $product->fresh()->stok);

        // 2. Opname status updated to posted
        $this->assertEquals('posted', $opname->fresh()->status);

        // 3. General journal entry created
        $this->assertDatabaseHas('bl_general_journals_t', [
            'reference_id' => $opname->id,
            'source_type' => 'STOCK_OPNAME',
        ]);

        // 4. Journal items: Debit 6106 (Shrinkage Loss) Rp 20.000, Kredit 1104 (Persediaan) Rp 20.000
        $this->assertDatabaseHas('bl_journal_items_t', [
            'kode_coa' => '6106',
            'debit' => 20000,
            'kredit' => 0,
        ]);
        $this->assertDatabaseHas('bl_journal_items_t', [
            'kode_coa' => '1104',
            'debit' => 0,
            'kredit' => 20000,
        ]);
    }

    public function test_post_action_creates_gain_journal_and_adjusts_physical_stock(): void
    {
        $this->actingAs($this->user);

        $product = Product::create([
            'sku' => 'GAIN-01',
            'nama' => 'Mawar Putih Lebih',
            'kategori' => 'bunga',
            'harga_beli' => 12000,
            'harga_jual' => 20000,
            'stok' => 50,
            'is_active' => true,
        ]);

        $opname = StockOpname::create([
            'reference_number' => 'SO-GAIN-001',
            'date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_qty' => 50,
            'physical_qty' => 53,
            'variance' => 3,
            'unit_cost' => 12000,
            'total_value' => 36000,
        ]);

        // Post from Edit page action
        Livewire::test(EditStockOpname::class, ['record' => $opname->id])
            ->callAction('post')
            ->assertHasNoActionErrors();

        // 1. Stock updated
        $this->assertEquals(53, $product->fresh()->stok);

        // 2. Status updated
        $this->assertEquals('posted', $opname->fresh()->status);

        // 3. Journal items: Debit 1104 (Persediaan) Rp 36.000, Kredit 4104 (Pendapatan Gain) Rp 36.000
        $this->assertDatabaseHas('bl_journal_items_t', [
            'kode_coa' => '1104',
            'debit' => 36000,
            'kredit' => 0,
        ]);
        $this->assertDatabaseHas('bl_journal_items_t', [
            'kode_coa' => '4104',
            'debit' => 0,
            'kredit' => 36000,
        ]);
    }

    public function test_post_action_from_table_works_properly(): void
    {
        $this->actingAs($this->user);

        $product = Product::create([
            'sku' => 'TBL-01',
            'nama' => 'Bunga Meja',
            'kategori' => 'bunga',
            'harga_beli' => 15000,
            'harga_jual' => 25000,
            'stok' => 20,
            'is_active' => true,
        ]);

        $opname = StockOpname::create([
            'reference_number' => 'SO-TBL-001',
            'date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_qty' => 20,
            'physical_qty' => 18,
            'variance' => -2,
            'unit_cost' => 15000,
            'total_value' => -30000,
        ]);

        // Call record action 'post' from ListStockOpnames table
        Livewire::test(ListStockOpnames::class)
            ->callTableAction('post', $opname)
            ->assertHasNoTableActionErrors();

        $this->assertEquals(18, $product->fresh()->stok);
        $this->assertEquals('posted', $opname->fresh()->status);
    }

    public function test_posted_stock_opname_hides_edit_and_post_actions_on_view_page(): void
    {
        $this->actingAs($this->user);

        $opname = StockOpname::create([
            'reference_number' => 'SO-IMMUTABLE-001',
            'date' => now()->toDateString(),
            'status' => 'posted',
        ]);

        Livewire::test(ViewStockOpname::class, ['record' => $opname->id])
            ->assertActionHidden('post')
            ->assertActionHidden('edit');
    }

    public function test_post_action_fails_validation_if_accounting_period_is_closed(): void
    {
        $this->actingAs($this->user);

        $this->period->update(['status' => 'closed']);

        $product = Product::create([
            'sku' => 'CLOSED-01',
            'nama' => 'Bunga Krisan',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 15000,
            'stok' => 50,
            'is_active' => true,
        ]);

        $opname = StockOpname::create([
            'reference_number' => 'SO-CLOSED-001',
            'date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_qty' => 50,
            'physical_qty' => 45,
            'variance' => -5,
            'unit_cost' => 10000,
            'total_value' => -50000,
        ]);

        Livewire::test(ViewStockOpname::class, ['record' => $opname->id])
            ->callAction('post');

        // Status must remain draft, stock unchanged
        $this->assertEquals('draft', $opname->fresh()->status);
        $this->assertEquals(50, $product->fresh()->stok);
        $this->assertDatabaseMissing('bl_general_journals_t', [
            'reference_id' => $opname->id,
        ]);
    }

    public function test_post_action_with_both_loss_and_gain_items_balances_journal(): void
    {
        $this->actingAs($this->user);

        $productLoss = Product::create([
            'sku' => 'MIX-LOSS',
            'nama' => 'Mawar Merah Mix',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 15000,
            'stok' => 100,
            'is_active' => true,
        ]);

        $productGain = Product::create([
            'sku' => 'MIX-GAIN',
            'nama' => 'Mawar Putih Mix',
            'kategori' => 'bunga',
            'harga_beli' => 12000,
            'harga_jual' => 18000,
            'stok' => 50,
            'is_active' => true,
        ]);

        $opname = StockOpname::create([
            'reference_number' => 'SO-MIX-001',
            'date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $productLoss->id,
            'system_qty' => 100,
            'physical_qty' => 98,
            'variance' => -2,
            'unit_cost' => 10000,
            'total_value' => -20000,
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $productGain->id,
            'system_qty' => 50,
            'physical_qty' => 53,
            'variance' => 3,
            'unit_cost' => 12000,
            'total_value' => 36000,
        ]);

        Livewire::test(ViewStockOpname::class, ['record' => $opname->id])
            ->callAction('post')
            ->assertHasNoActionErrors();

        // Product stock updated
        $this->assertEquals(98, $productLoss->fresh()->stok);
        $this->assertEquals(53, $productGain->fresh()->stok);

        // General journal balanced: Total Debit == Total Credit
        $journal = \App\Models\GeneralJournal::where('reference_id', $opname->id)
            ->where('source_type', 'STOCK_OPNAME')
            ->first();

        $this->assertNotNull($journal);
        $totalDebit = $journal->items()->sum('debit');
        $totalKredit = $journal->items()->sum('kredit');

        $this->assertEquals(56000, $totalDebit);
        $this->assertEquals(56000, $totalKredit);
    }

    public function test_post_action_with_zero_variance_does_not_create_empty_journal(): void
    {
        $this->actingAs($this->user);

        $product = Product::create([
            'sku' => 'ZERO-01',
            'nama' => 'Bunga Sedap Malam',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 15000,
            'stok' => 100,
            'is_active' => true,
        ]);

        $opname = StockOpname::create([
            'reference_number' => 'SO-ZERO-001',
            'date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_qty' => 100,
            'physical_qty' => 100,
            'variance' => 0,
            'unit_cost' => 10000,
            'total_value' => 0,
        ]);

        Livewire::test(ViewStockOpname::class, ['record' => $opname->id])
            ->callAction('post')
            ->assertHasNoActionErrors();

        $this->assertEquals('posted', $opname->fresh()->status);
        $this->assertEquals(100, $product->fresh()->stok);

        // No journal needed since variance is zero
        $this->assertDatabaseMissing('bl_general_journals_t', [
            'reference_id' => $opname->id,
            'source_type' => 'STOCK_OPNAME',
        ]);
    }

    public function test_stock_opname_filter_by_status_on_list_table(): void
    {
        $this->actingAs($this->user);

        $draftOpname = StockOpname::create([
            'reference_number' => 'SO-FILTER-DRAFT',
            'date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $postedOpname = StockOpname::create([
            'reference_number' => 'SO-FILTER-POSTED',
            'date' => now()->toDateString(),
            'status' => 'posted',
        ]);

        Livewire::test(ListStockOpnames::class)
            ->assertCanSeeTableRecords([$draftOpname, $postedOpname])
            ->filterTable('status', 'draft')
            ->assertCanSeeTableRecords([$draftOpname])
            ->assertCanNotSeeTableRecords([$postedOpname])
            ->filterTable('status', 'posted')
            ->assertCanSeeTableRecords([$postedOpname])
            ->assertCanNotSeeTableRecords([$draftOpname]);
    }

    public function test_stock_opname_delete_action_is_available_for_draft_on_edit_page(): void
    {
        $this->actingAs($this->user);

        $opname = StockOpname::create([
            'reference_number' => 'SO-DELETE-001',
            'date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        Livewire::test(EditStockOpname::class, ['record' => $opname->id])
            ->assertActionVisible('delete')
            ->callAction('delete')
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('bl_stock_opnames_t', [
            'id' => $opname->id,
        ]);
    }

    public function test_stock_opname_overview_widget_renders_correct_stats(): void
    {
        $this->actingAs($this->user);

        // 1. Initial empty state
        Livewire::test(\App\Filament\Resources\StockOpnames\Widgets\StockOpnameOverview::class)
            ->assertSee('Total Fisik Terinput')
            ->assertSee('0 pcs')
            ->assertSee('Dokumen Opname');

        // 2. Create Stock Opnames with items
        $product = Product::create([
            'sku' => 'FLW-ROS-001',
            'nama' => 'Mawar Merah',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 15000,
            'stok' => 20,
            'is_active' => true,
        ]);

        $opname = StockOpname::create([
            'reference_number' => 'SO-STATS-001',
            'date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_qty' => 20,
            'physical_qty' => 25,
            'variance' => 5,
            'unit_cost' => 10000,
            'total_value' => 50000,
        ]);

        Livewire::test(\App\Filament\Resources\StockOpnames\Widgets\StockOpnameOverview::class)
            ->assertSee('Total Fisik Terinput')
            ->assertSee('25 pcs')
            ->assertSee('1 Produk')
            ->assertSee('1 Dokumen')
            ->assertSee('+5 pcs');
    }
}
