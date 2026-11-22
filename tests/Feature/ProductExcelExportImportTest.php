<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductExcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class ProductExcelExportImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_export_products_excel_without_stock_column(): void
    {
        Product::create([
            'sku' => 'FLW-ROSE-RED',
            'nama' => 'Red Rose Holland',
            'kategori' => 'bunga',
            'harga_beli' => 12000,
            'harga_jual' => 25000,
            'harga_vendor' => 22000,
            'harga_dekor' => 20000,
            'stok' => 50,
            'is_active' => true,
        ]);

        $service = app(ProductExcelService::class);
        $response = $service->exportProductsExcel();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));

        $filePath = $response->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($filePath) === true);

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertNotEmpty($sheetXml);
        // Header assertions
        $this->assertStringContainsString('sku', $sheetXml);
        $this->assertStringContainsString('nama', $sheetXml);
        $this->assertStringContainsString('kategori', $sheetXml);
        $this->assertStringContainsString('harga_beli', $sheetXml);
        $this->assertStringContainsString('harga_jual', $sheetXml);
        $this->assertStringContainsString('harga_vendor', $sheetXml);
        $this->assertStringContainsString('harga_dekor', $sheetXml);
        $this->assertStringContainsString('status', $sheetXml);

        // Crucial requirement: No stock column header!
        $this->assertStringNotContainsString('stok_fisik', $sheetXml);
        $this->assertStringNotContainsString('stok_sistem', $sheetXml);
        $this->assertStringNotContainsString('>stok<', $sheetXml);

        // Data assertions
        $this->assertStringContainsString('FLW-ROSE-RED', $sheetXml);
        $this->assertStringContainsString('Red Rose Holland', $sheetXml);
    }

    public function test_can_generate_template_excel(): void
    {
        $service = app(ProductExcelService::class);
        $response = $service->generateTemplateExcel();

        $this->assertEquals(200, $response->getStatusCode());
        $filePath = $response->getFile()->getPathname();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($filePath) === true);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertNotEmpty($sheetXml);
        $this->assertStringContainsString('FLW-ROS-RED', $sheetXml);
        $this->assertStringContainsString('PKG-WRAP-KRAFT', $sheetXml);
    }

    public function test_import_updates_tier_prices_and_leaves_stock_untouched(): void
    {
        // 1. Existing product with specific stock and initial prices
        $existing = Product::create([
            'sku' => 'FLW-TULIP-WHT',
            'nama' => 'White Tulip',
            'kategori' => 'bunga',
            'harga_beli' => 15000,
            'harga_jual' => 30000,
            'harga_vendor' => 28000,
            'harga_dekor' => 26000,
            'stok' => 77, // Existing stock must remain 77
            'is_active' => true,
        ]);

        // 2. Prepare test CSV file with updated tier prices and 1 new product
        $csvContent = implode("\n", [
            'sku,nama,kategori,harga_beli,harga_jual,harga_vendor,harga_dekor,status',
            'FLW-TULIP-WHT,White Tulip Premium,bunga,16000,35000,32000,29000,aktif',
            'PKG-BOX-VINTAGE,Box Vintage Ribbon,packaging,5000,12000,10000,9000,aktif',
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_prod_').'.csv';
        file_put_contents($tempFile, $csvContent);

        $service = app(ProductExcelService::class);
        $result = $service->importExcel($tempFile);

        @unlink($tempFile);

        $this->assertEquals(2, $result['total_rows']);
        $this->assertEquals(1, $result['updated_count']);
        $this->assertEquals(1, $result['created_count']);

        // Check updated product
        $existing->refresh();
        $this->assertEquals('White Tulip Premium', $existing->nama);
        $this->assertEquals(16000, (float) $existing->harga_beli);
        $this->assertEquals(35000, (float) $existing->harga_jual);
        $this->assertEquals(32000, (float) $existing->harga_vendor);
        $this->assertEquals(29000, (float) $existing->harga_dekor);
        // CRITICAL: Stock was NOT touched!
        $this->assertEquals(77, (int) $existing->stok);

        // Check newly created product
        $newProduct = Product::where('sku', 'PKG-BOX-VINTAGE')->first();
        $this->assertNotNull($newProduct);
        $this->assertEquals('Box Vintage Ribbon', $newProduct->nama);
        $this->assertEquals('packaging', $newProduct->kategori);
        $this->assertEquals(12000, (float) $newProduct->harga_jual);
        $this->assertEquals(10000, (float) $newProduct->harga_vendor);
        $this->assertEquals(9000, (float) $newProduct->harga_dekor);
        // Newly created product starts with 0 stock
        $this->assertEquals(0, (int) $newProduct->stok);
    }

    public function test_import_throws_exception_on_invalid_category_or_missing_columns(): void
    {
        $csvContent = implode("\n", [
            'sku,nama,kategori,harga_jual',
            'TEST-01,Test Item,elektronik,50000',
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_err_').'.csv';
        file_put_contents($tempFile, $csvContent);

        $service = app(ProductExcelService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Kategori 'elektronik' tidak valid");

        try {
            $service->importExcel($tempFile);
        } finally {
            @unlink($tempFile);
        }
    }

    public function test_list_products_page_renders_export_and_import_actions(): void
    {
        $user = User::create([
            'name' => 'Super Admin Test',
            'email' => 'superadmin_export_test@example.com',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(ListProducts::class)
            ->assertStatus(200)
            ->assertActionExists('exportExcel')
            ->assertActionExists('downloadTemplate')
            ->assertActionExists('importExcel');
    }
}
