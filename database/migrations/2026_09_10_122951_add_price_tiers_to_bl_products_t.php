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
        Schema::table('bl_products_t', function (Blueprint $table) {
            $table->decimal('harga_vendor', 15, 2)->default(0)->after('harga_jual');
            $table->decimal('harga_dekor', 15, 2)->default(0)->after('harga_vendor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bl_products_t', function (Blueprint $table) {
            $table->dropColumn(['harga_vendor', 'harga_dekor']);
        });
    }
};
