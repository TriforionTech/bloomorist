<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambah soft deletes pada bl_customers_t
        Schema::table('bl_customers_t', function (Blueprint $table) {
            $table->softDeletes();
        });

        // 2. Tambah kolom customer_name sebagai snapshot pada bl_invoices_t
        Schema::table('bl_invoices_t', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('customer_id');
        });

        // 3. Backfill data customer_name untuk invoice yang sudah ada
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('UPDATE bl_invoices_t SET customer_name = (SELECT nama FROM bl_customers_t WHERE bl_customers_t.id = bl_invoices_t.customer_id) WHERE customer_name IS NULL AND customer_id IS NOT NULL');
        } else {
            DB::table('bl_invoices_t')
                ->join('bl_customers_t', 'bl_invoices_t.customer_id', '=', 'bl_customers_t.id')
                ->whereNull('bl_invoices_t.customer_name')
                ->update([
                    'bl_invoices_t.customer_name' => DB::raw('bl_customers_t.nama'),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bl_invoices_t', function (Blueprint $table) {
            $table->dropColumn('customer_name');
        });

        Schema::table('bl_customers_t', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
