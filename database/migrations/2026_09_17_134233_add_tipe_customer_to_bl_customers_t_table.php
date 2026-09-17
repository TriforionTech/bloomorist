<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bl_customers_t', function (Blueprint $table) {
            $table->enum('tipe_customer', ['toko', 'vendor', 'dekor'])->default('toko')->after('nama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bl_customers_t', function (Blueprint $table) {
            $table->dropColumn('tipe_customer');
        });
    }
};
