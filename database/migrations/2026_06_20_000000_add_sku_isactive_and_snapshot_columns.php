<?php

use App\Models\Product;
use App\Services\SkuGeneratorService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 1. bl_products_t: tambah kolom sku (unique) dan is_active (boolean)
     * 2. bl_invoice_items_t: tambah snapshot_sku, snapshot_name, FK SET NULL
     * 3. Backfill SKU untuk data existing
     * 4. Backfill snapshot data untuk invoice items existing
     */
    public function up(): void
    {
        // === 1. bl_products_t: tambah sku + is_active ===
        Schema::table('bl_products_t', function (Blueprint $table) {
            $table->string('sku')->nullable()->unique()->after('id');
            $table->boolean('is_active')->default(true)->after('stok');
        });

        // === 2. bl_invoice_items_t: tambah snapshot + FK SET NULL ===
        Schema::table('bl_invoice_items_t', function (Blueprint $table) {
            $table->string('snapshot_sku')->default('')->after('product_id');
            $table->string('snapshot_name')->default('')->after('snapshot_sku');

            // Tambah FK constraint dengan ON DELETE SET NULL
            $table->foreign('product_id')
                ->references('id')
                ->on('bl_products_t')
                ->onDelete('set null');
        });

        // === 3. Backfill SKU untuk produk existing ===
        $this->backfillProductSkus();

        // === 4. Backfill snapshot untuk invoice items existing ===
        $this->backfillInvoiceItemSnapshots();

        // === 5. Setelah backfill, buat sku NOT NULL ===
        Schema::table('bl_products_t', function (Blueprint $table) {
            $table->string('sku')->nullable(false)->change();
        });

        // === 6. Setelah backfill, buat snapshot NOT NULL ===
        Schema::table('bl_invoice_items_t', function (Blueprint $table) {
            $table->string('snapshot_sku')->default(null)->change();
            $table->string('snapshot_name')->default(null)->change();
        });
    }

    /**
     * Backfill SKU untuk semua produk yang sudah ada.
     * Box = PKG-BOX-001, Wrapping = PKG-WRP-001, sisanya auto-generate.
     */
    private function backfillProductSkus(): void
    {
        $products = DB::table('bl_products_t')->whereNull('sku')->get();

        foreach ($products as $product) {
            $sku = match (strtolower($product->nama)) {
                'box'      => 'PKG-BOX-001',
                'wrapping' => 'PKG-WRP-001',
                default    => SkuGeneratorService::generate(
                    $product->kategori ?? 'others',
                    $product->nama
                ),
            };

            DB::table('bl_products_t')
                ->where('id', $product->id)
                ->update(['sku' => $sku]);
        }
    }

    /**
     * Backfill snapshot_sku dan snapshot_name untuk invoice items existing.
     * Ambil data dari relasi product yang masih ada.
     */
    private function backfillInvoiceItemSnapshots(): void
    {
        DB::table('bl_invoice_items_t')
            ->where('snapshot_name', '')
            ->chunkById(200, function ($items) {
                foreach ($items as $item) {
                    if ($item->product_id) {
                        $product = DB::table('bl_products_t')
                            ->where('id', $item->product_id)
                            ->first();

                        if ($product) {
                            DB::table('bl_invoice_items_t')
                                ->where('id', $item->id)
                                ->update([
                                    'snapshot_sku'  => $product->sku ?? 'UNKNOWN',
                                    'snapshot_name' => $product->nama ?? 'Unknown Product',
                                ]);
                            continue;
                        }
                    }

                    // Produk tidak ditemukan — fallback
                    DB::table('bl_invoice_items_t')
                        ->where('id', $item->id)
                        ->update([
                            'snapshot_sku'  => 'DELETED',
                            'snapshot_name' => 'Deleted Product',
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bl_invoice_items_t', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['snapshot_sku', 'snapshot_name']);
        });

        Schema::table('bl_products_t', function (Blueprint $table) {
            $table->dropColumn(['sku', 'is_active']);
        });
    }
};
