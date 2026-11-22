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
        Schema::table('bl_invoices_t', function (Blueprint $table) {
            $table->string('pricing_tier', 20)->default('toko')->after('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bl_invoices_t', function (Blueprint $table) {
            $table->dropColumn('pricing_tier');
        });
    }
};
