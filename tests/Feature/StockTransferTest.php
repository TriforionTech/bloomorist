<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat akun yang dibutuhkan
        ChartOfAccount::create([
            'kode_akun' => '1104',
            'nama_akun' => 'Persediaan Barang',
            'saldo_normal' => 'Debit',
            'kategori' => 'Aset',
        ]);
        ChartOfAccount::create([
            'kode_akun' => '4104',
            'nama_akun' => 'Pendapatan lain-lain',
            'saldo_normal' => 'Kredit',
            'kategori' => 'Pendapatan Lain-Lain',
        ]);
        ChartOfAccount::create([
            'kode_akun' => '6108',
            'nama_akun' => 'Beban Lain-Lain',
            'saldo_normal' => 'Debit',
            'kategori' => 'Beban',
        ]);

        \App\Models\AccountingPeriod::create([
            'label' => 'Periode Test',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'open',
        ]);
    }

    public function test_stock_transfer_creates_journal_and_adjusts_stock_with_gain()
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $sourceProduct = Product::create([
            'sku' => 'SKU-001',
            'nama' => 'Mawar Merah',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 15000,
            'harga_vendor' => 12000,
            'harga_dekor' => 11000,
            'stok' => 50,
            'is_active' => true,
        ]);

        $targetProduct = Product::create([
            'sku' => 'SKU-002',
            'nama' => 'Mawar Pilihan',
            'kategori' => 'bunga',
            'harga_beli' => 12000,
            'harga_jual' => 20000,
            'harga_vendor' => 15000,
            'harga_dekor' => 13000,
            'stok' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        // Simulate Livewire form submission
        \Livewire\Livewire::test(\App\Filament\Resources\StockTransfers\Pages\ManageStockTransfers::class)
            ->callAction('create', data: [
                'tanggal' => now()->toDateString(),
                'source_product_id' => $sourceProduct->id,
                'target_product_id' => $targetProduct->id,
                'quantity' => 10,
                'keterangan' => 'Test Gain',
            ])
            ->assertHasNoActionErrors();

        // Check stock
        $this->assertEquals(40, $sourceProduct->fresh()->stok);
        $this->assertEquals(20, $targetProduct->fresh()->stok);

        // Check journal
        $this->assertDatabaseHas('bl_general_journals_t', [
            'source_type' => 'STOCK_TRANSFER',
        ]);

        // Source = 10 * 10000 = 100000
        // Target = 10 * 12000 = 120000
        // Gain = 20000
        $this->assertDatabaseHas('bl_journal_items_t', [
            'kode_coa' => '1104',
            'debit' => 120000,
            'kredit' => 0,
        ]);
        $this->assertDatabaseHas('bl_journal_items_t', [
            'kode_coa' => '1104',
            'debit' => 0,
            'kredit' => 100000,
        ]);
        $this->assertDatabaseHas('bl_journal_items_t', [
            'kode_coa' => '4104',
            'debit' => 0,
            'kredit' => 20000, // Gain
        ]);
    }

    public function test_stock_transfer_creates_journal_and_adjusts_stock_with_loss()
    {
        $user = User::create([
            'name' => 'Admin2',
            'email' => 'admin2@example.com',
            'password' => bcrypt('password'),
        ]);

        $sourceProduct = Product::create([
            'sku' => 'SKU-003',
            'nama' => 'Bunga A',
            'kategori' => 'bunga',
            'harga_beli' => 15000,
            'harga_jual' => 25000,
            'harga_vendor' => 20000,
            'harga_dekor' => 18000,
            'stok' => 50,
            'is_active' => true,
        ]);

        $targetProduct = Product::create([
            'sku' => 'SKU-004',
            'nama' => 'Bunga B',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 15000,
            'harga_vendor' => 12000,
            'harga_dekor' => 11000,
            'stok' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        // Simulate Livewire form submission
        \Livewire\Livewire::test(\App\Filament\Resources\StockTransfers\Pages\ManageStockTransfers::class)
            ->callAction('create', data: [
                'tanggal' => now()->toDateString(),
                'source_product_id' => $sourceProduct->id,
                'target_product_id' => $targetProduct->id,
                'quantity' => 5,
                'keterangan' => 'Test Loss',
            ])
            ->assertHasNoActionErrors();

        // Check stock
        $this->assertEquals(45, $sourceProduct->fresh()->stok);
        $this->assertEquals(15, $targetProduct->fresh()->stok);

        // Source = 5 * 15000 = 75000
        // Target = 5 * 10000 = 50000
        // Loss = 25000
        $this->assertDatabaseHas('bl_journal_items_t', [
            'kode_coa' => '1104',
            'debit' => 50000,
            'kredit' => 0,
        ]);
        $this->assertDatabaseHas('bl_journal_items_t', [
            'kode_coa' => '1104',
            'debit' => 0,
            'kredit' => 75000,
        ]);
        $this->assertDatabaseHas('bl_journal_items_t', [
            'kode_coa' => '6108',
            'debit' => 25000, // Loss
            'kredit' => 0,
        ]);
    }
}
