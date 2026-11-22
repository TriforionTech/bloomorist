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
            $table->index(['status', 'issued_date']);
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bl_invoices_t', function (Blueprint $table) {
            $table->dropIndex(['status', 'issued_date']);
            $table->dropIndex(['customer_id']);
        });
    }
};
