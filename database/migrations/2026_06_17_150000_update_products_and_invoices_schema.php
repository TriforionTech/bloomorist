<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Operasi 1: Rename kolom bl_products_t (hilangkan suffix _barang)
     * Operasi 2: Tambah kolom kategori ke bl_products_t
     * Operasi 3: Drop kolom membership_id dari bl_invoices_t
     */
    public function up(): void
    {
        // Operasi 1 & 2: bl_products_t
        Schema::table('bl_products_t', function (Blueprint $table) {
            $table->renameColumn('nama_barang', 'nama');
            $table->renameColumn('harga_beli_barang', 'harga_beli');
            $table->renameColumn('harga_jual_barang', 'harga_jual');
            $table->renameColumn('stok_barang', 'stok');
        });

        Schema::table('bl_products_t', function (Blueprint $table) {
            $table->enum('kategori', ['bunga', 'packaging', 'others'])
                ->default('bunga')
                ->after('nama');
        });

        // Operasi 3: bl_invoices_t
        Schema::table('bl_invoices_t', function (Blueprint $table) {
            $table->dropColumn('membership_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert bl_products_t
        Schema::table('bl_products_t', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });

        Schema::table('bl_products_t', function (Blueprint $table) {
            $table->renameColumn('nama', 'nama_barang');
            $table->renameColumn('harga_beli', 'harga_beli_barang');
            $table->renameColumn('harga_jual', 'harga_jual_barang');
            $table->renameColumn('stok', 'stok_barang');
        });

        // Revert bl_invoices_t
        Schema::table('bl_invoices_t', function (Blueprint $table) {
            $table->unsignedBigInteger('membership_id')->nullable();
        });
    }
};
