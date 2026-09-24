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

    protected function createTestExcelFile(array $rows): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_so_xlsx_');
        $zip = new \ZipArchive;
        $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>
</workbook>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $sheetXml .= '<row r="1">
            <c r="A1" t="inlineStr"><is><t>product_id</t></is></c>
            <c r="B1" t="inlineStr"><is><t>sku</t></is></c>
            <c r="C1" t="inlineStr"><is><t>nama_produk</t></is></c>
            <c r="D1" t="inlineStr"><is><t>kategori</t></is></c>
            <c r="E1" t="inlineStr"><is><t>stok_sistem</t></is></c>
            <c r="F1" t="inlineStr"><is><t>harga_beli</t></is></c>
            <c r="G1" t="inlineStr"><is><t>stok_fisik</t></is></c>
            <c r="H1" t="inlineStr"><is><t>catatan</t></is></c>
        </row>';

        $rowNum = 2;
        foreach ($rows as $r) {
            $sheetXml .= '<row r="'.$rowNum.'">';
            $sheetXml .= '<c r="A'.$rowNum.'"><v>'.$r['product_id'].'</v></c>';
            $sheetXml .= '<c r="B'.$rowNum.'" t="inlineStr"><is><t>'.$r['sku'].'</t></is></c>';
            $sheetXml .= '<c r="C'.$rowNum.'" t="inlineStr"><is><t>'.($r['nama_produk'] ?? '').'</t></is></c>';
            $sheetXml .= '<c r="D'.$rowNum.'" t="inlineStr"><is><t>'.($r['kategori'] ?? '').'</t></is></c>';
            $sheetXml .= '<c r="E'.$rowNum.'"><v>'.($r['stok_sistem'] ?? 0).'</v></c>';
            $sheetXml .= '<c r="F'.$rowNum.'"><v>'.($r['harga_beli'] ?? 0).'</v></c>';
            $sheetXml .= '<c r="G'.$rowNum.'"><v>'.$r['stok_fisik'].'</v></c>';
            $sheetXml .= '<c r="H'.$rowNum.'" t="inlineStr"><is><t>'.($r['catatan'] ?? '').'</t></is></c>';
            $sheetXml .= '</row>';
            $rowNum++;
        }
        $sheetXml .= '</sheetData></worksheet>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return $tempPath;
    }

    public function test_can_import_stock_opname_via_excel_as_draft(): void
    {
        $this->actingAs($this->user);

        $prodA = Product::create([
            'sku' => 'PROD-EX-A',
            'nama' => 'Produk Excel A',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 15000,
            'stok' => 50,
            'is_active' => true,
        ]);

        $prodB = Product::create([
            'sku' => 'PROD-EX-B',
            'nama' => 'Produk Excel B',
            'kategori' => 'bunga',
            'harga_beli' => 20000,
            'harga_jual' => 30000,
            'stok' => 30,
            'is_active' => true,
        ]);

        $tempPath = $this->createTestExcelFile([
            ['product_id' => $prodA->id, 'sku' => 'PROD-EX-A', 'nama_produk' => 'Produk Excel A', 'stok_fisik' => '45', 'catatan' => 'Kurang 5 pcs'],
            ['product_id' => $prodB->id, 'sku' => 'PROD-EX-B', 'nama_produk' => 'Produk Excel B', 'stok_fisik' => '32', 'catatan' => 'Surplus 2 pcs'],
        ]);

        $service = app(\App\Services\StockOpnameExcelService::class);
        $result = $service->importExcel($tempPath, [
            'reference_number' => 'SO-EX-TEST-001',
            'date' => now()->toDateString(),
            'notes' => 'Import Excel Opname Test',
            'post_immediately' => false,
        ]);

        @unlink($tempPath);

        $this->assertEquals(2, $result['total_items']);
        $this->assertFalse($result['posted']);

        $opname = $result['stock_opname'];
        $this->assertEquals('draft', $opname->status);
        $this->assertEquals('SO-EX-TEST-001', $opname->reference_number);

        // Verify items
        $this->assertDatabaseHas('bl_stock_opname_items_t', [
            'stock_opname_id' => $opname->id,
            'product_id' => $prodA->id,
            'system_qty' => 50,
            'physical_qty' => 45,
            'variance' => -5,
            'total_value' => -50000,
            'notes' => 'Kurang 5 pcs',
        ]);

        $this->assertDatabaseHas('bl_stock_opname_items_t', [
            'stock_opname_id' => $opname->id,
            'product_id' => $prodB->id,
            'system_qty' => 30,
            'physical_qty' => 32,
            'variance' => 2,
            'total_value' => 40000,
            'notes' => 'Surplus 2 pcs',
        ]);

        // Stock in products table should NOT have changed yet because status is draft
        $this->assertEquals(50, $prodA->fresh()->stok);
        $this->assertEquals(30, $prodB->fresh()->stok);
    }

    public function test_can_import_stock_opname_via_excel_with_immediate_posting(): void
    {
        $this->actingAs($this->user);

        $product = Product::create([
            'sku' => 'PROD-POST-EX',
            'nama' => 'Bunga Anggrek Excel',
            'kategori' => 'bunga',
            'harga_beli' => 50000,
            'harga_jual' => 80000,
            'stok' => 10,
            'is_active' => true,
        ]);

        $tempPath = $this->createTestExcelFile([
            ['product_id' => $product->id, 'sku' => 'PROD-POST-EX', 'nama_produk' => 'Bunga Anggrek Excel', 'stok_fisik' => '8', 'catatan' => 'Rusak 2 tangkai'],
        ]);

        $service = app(\App\Services\StockOpnameExcelService::class);
        $result = $service->importExcel($tempPath, [
            'reference_number' => 'SO-POST-EX-001',
            'date' => now()->toDateString(),
            'notes' => 'Direct post test',
            'post_immediately' => true,
        ]);

        @unlink($tempPath);

        $this->assertTrue($result['posted']);
        $opname = $result['stock_opname'];
        $this->assertEquals('posted', $opname->status);

        // Product physical stock must be updated to 8
        $this->assertEquals(8, $product->fresh()->stok);

        // General journal must exist
        $this->assertDatabaseHas('bl_general_journals_t', [
            'source_type' => 'STOCK_OPNAME',
            'reference_id' => $opname->id,
        ]);
    }

    public function test_import_rejects_excel_if_sku_tampered_or_invalid(): void
    {
        $this->actingAs($this->user);

        $product = Product::create([
            'sku' => 'SKU-ORIGINAL-EX',
            'nama' => 'Produk Asli Excel',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 15000,
            'stok' => 20,
            'is_active' => true,
        ]);

        // SKU in Excel does not match SKU in DB for that product_id
        $tempPath = $this->createTestExcelFile([
            ['product_id' => $product->id, 'sku' => 'SKU-MANIPULATED-EX', 'nama_produk' => 'Produk Asli Excel', 'stok_fisik' => '18'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ketidakcocokan data terdeteksi');

        try {
            app(\App\Services\StockOpnameExcelService::class)->importExcel($tempPath, [
                'reference_number' => 'SO-TAMPER-EX-001',
                'date' => now()->toDateString(),
            ]);
        } finally {
            @unlink($tempPath);
        }
    }

    public function test_import_rejects_negative_or_non_integer_physical_qty(): void
    {
        $this->actingAs($this->user);

        $product = Product::create([
            'sku' => 'SKU-NEG-EX',
            'nama' => 'Produk Negatif Excel',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 15000,
            'stok' => 20,
            'is_active' => true,
        ]);

        $tempPath = $this->createTestExcelFile([
            ['product_id' => $product->id, 'sku' => 'SKU-NEG-EX', 'nama_produk' => 'Produk Negatif Excel', 'stok_fisik' => '-5'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak valid. Harus berupa angka bulat >= 0');

        try {
            app(\App\Services\StockOpnameExcelService::class)->importExcel($tempPath, [
                'reference_number' => 'SO-NEG-EX-001',
                'date' => now()->toDateString(),
            ]);
        } finally {
            @unlink($tempPath);
        }
    }

    public function test_import_rejects_if_accounting_period_is_closed(): void
    {
        $this->actingAs($this->user);

        $this->period->update(['status' => 'closed']);

        $product = Product::create([
            'sku' => 'SKU-CLOSED-EX',
            'nama' => 'Produk Closed Excel',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 15000,
            'stok' => 20,
            'is_active' => true,
        ]);

        $tempPath = $this->createTestExcelFile([
            ['product_id' => $product->id, 'sku' => 'SKU-CLOSED-EX', 'nama_produk' => 'Produk Closed Excel', 'stok_fisik' => '19'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah ditutup (Closed)');

        try {
            app(\App\Services\StockOpnameExcelService::class)->importExcel($tempPath, [
                'reference_number' => 'SO-CLOSED-EX-001',
                'date' => now()->toDateString(),
            ]);
        } finally {
            @unlink($tempPath);
        }
    }

    public function test_filament_list_page_has_excel_actions_and_no_csv(): void
    {
        $this->actingAs($this->user);

        Livewire::test(ListStockOpnames::class)
            ->assertActionExists('downloadTemplate')
            ->assertActionExists('importExcel')
            ->assertActionDoesNotExist('downloadTemplateCsv');
    }

    public function test_filament_list_page_can_execute_import_excel_action(): void
    {
        $this->actingAs($this->user);

        \Illuminate\Support\Facades\Storage::fake('local');

        $product = Product::create([
            'sku' => 'SKU-LW-EXCEL',
            'nama' => 'Produk Livewire Excel',
            'kategori' => 'bunga',
            'harga_beli' => 15000,
            'harga_jual' => 25000,
            'stok' => 40,
            'is_active' => true,
        ]);

        $service = app(\App\Services\StockOpnameExcelService::class);
        $tempXlsx = tempnam(sys_get_temp_dir(), 'test_lw_xlsx_');
        $response = $service->generateTemplateExcel();
        copy($response->getFile()->getPathname(), $tempXlsx);

        // Edit cell G2 in xlsx to enter 38
        $zip = new \ZipArchive;
        $zip->open($tempXlsx);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sheetXml = str_replace('<c r="G2"></c>', '<c r="G2"><v>38</v></c>', $sheetXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $uploadedFile = new \Illuminate\Http\UploadedFile($tempXlsx, 'opname_test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $storedPath = $uploadedFile->store('temp-stock-opname', 'local');

        Livewire::test(ListStockOpnames::class)
            ->callAction('importExcel', data: [
                'date' => now()->toDateString(),
                'reference_number' => 'SO-LW-EXCEL-001',
                'notes' => 'Imported via Excel modal',
                'file' => [$storedPath],
                'post_immediately' => false,
            ])
            ->assertHasNoActionErrors();

        @unlink($tempXlsx);

        $this->assertDatabaseHas('bl_stock_opnames_t', [
            'reference_number' => 'SO-LW-EXCEL-001',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('bl_stock_opname_items_t', [
            'product_id' => $product->id,
            'physical_qty' => 38,
            'variance' => -2,
        ]);
    }

    public function test_can_download_stock_opname_xlsx_template(): void
    {
        $this->actingAs($this->user);

        Product::create([
            'sku' => 'FLW-XLSX-01',
            'nama' => 'Mawar Hitam',
            'kategori' => 'bunga',
            'harga_beli' => 25000,
            'harga_jual' => 40000,
            'stok' => 12,
            'is_active' => true,
        ]);

        $service = app(\App\Services\StockOpnameExcelService::class);
        $response = $service->generateTemplateExcel();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(str_contains($response->headers->get('Content-Type'), 'spreadsheetml.sheet'));

        $filePath = $response->getFile()->getPathname();
        $this->assertFileExists($filePath);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($filePath));
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($sheetXml);
        $this->assertStringContainsString('FLW-XLSX-01', $sheetXml);
        $this->assertStringContainsString('Mawar Hitam', $sheetXml);
        $zip->close();
    }

    public function test_can_import_stock_opname_via_xlsx_as_draft(): void
    {
        $this->actingAs($this->user);

        $prodA = Product::create([
            'sku' => 'PROD-XLSX-A',
            'nama' => 'Produk XLSX A',
            'kategori' => 'bunga',
            'harga_beli' => 12000,
            'harga_jual' => 18000,
            'stok' => 25,
            'is_active' => true,
        ]);

        $service = app(\App\Services\StockOpnameExcelService::class);
        $tempXlsx = tempnam(sys_get_temp_dir(), 'test_import_xlsx_');

        // Generate template
        $response = $service->generateTemplateExcel();
        copy($response->getFile()->getPathname(), $tempXlsx);

        // Modify sheet1.xml in zip to fill physical_qty for prodA
        $zip = new \ZipArchive;
        $zip->open($tempXlsx);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sheetXml = str_replace('<c r="G2"></c>', '<c r="G2"><v>20</v></c>', $sheetXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $result = $service->importExcel($tempXlsx, [
            'reference_number' => 'SO-XLSX-TEST-001',
            'date' => now()->toDateString(),
            'notes' => 'Imported via XLSX',
            'post_immediately' => false,
        ]);

        @unlink($tempXlsx);

        $this->assertGreaterThanOrEqual(1, $result['total_items']);
        $this->assertFalse($result['posted']);

        $this->assertDatabaseHas('bl_stock_opnames_t', [
            'reference_number' => 'SO-XLSX-TEST-001',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('bl_stock_opname_items_t', [
            'product_id' => $prodA->id,
            'system_qty' => 25,
            'physical_qty' => 20,
            'variance' => -5,
            'total_value' => -60000,
        ]);
    }

    public function test_import_excel_rejects_corrupted_file(): void
    {
        $this->actingAs($this->user);

        $tempPath = tempnam(sys_get_temp_dir(), 'corrupt_');
        file_put_contents($tempPath, 'THIS IS NOT AN EXCEL FILE');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('bukan file Excel (.xlsx) yang valid');

        try {
            app(\App\Services\StockOpnameExcelService::class)->importExcel($tempPath, [
                'reference_number' => 'SO-CORRUPT-001',
                'date' => now()->toDateString(),
            ]);
        } finally {
            @unlink($tempPath);
        }
    }

    public function test_import_excel_rejects_missing_headers(): void
    {
        $this->actingAs($this->user);

        $tempPath = tempnam(sys_get_temp_dir(), 'no_headers_');
        $zip = new \ZipArchive;
        $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $sheetXml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>wrong_column</t></is></c></row></sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Format kolom Excel tidak valid');

        try {
            app(\App\Services\StockOpnameExcelService::class)->importExcel($tempPath, [
                'reference_number' => 'SO-HDR-001',
                'date' => now()->toDateString(),
            ]);
        } finally {
            @unlink($tempPath);
        }
    }

    public function test_import_excel_rejects_empty_physical_qty(): void
    {
        $this->actingAs($this->user);

        Product::create([
            'sku' => 'SKU-EMPTY-01',
            'nama' => 'Produk Tanpa Opname',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 15000,
            'stok' => 10,
            'is_active' => true,
        ]);

        $service = app(\App\Services\StockOpnameExcelService::class);
        $response = $service->generateTemplateExcel();
        $tempPath = $response->getFile()->getPathname();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Tidak ada produk dengan kolom 'stok_fisik' yang diisi");

        app(\App\Services\StockOpnameExcelService::class)->importExcel($tempPath, [
            'reference_number' => 'SO-BLANK-001',
            'date' => now()->toDateString(),
        ]);
    }
}
