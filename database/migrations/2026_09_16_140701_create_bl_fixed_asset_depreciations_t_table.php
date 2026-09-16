<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bl_fixed_asset_depreciations_t')) {
            Schema::create('bl_fixed_asset_depreciations_t', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fixed_asset_id')->constrained('bl_fixed_assets_t')->cascadeOnDelete();
                $table->foreignId('accounting_period_id')->constrained('bl_accounting_periods_t')->cascadeOnDelete();
                $table->decimal('monthly_depreciation', 18, 2);
                $table->decimal('accumulated_depreciation', 18, 2);
                $table->decimal('net_book_value', 18, 2);
                $table->foreignId('journal_entry_id')->nullable()->constrained('bl_general_journals_t')->nullOnDelete();
                $table->timestamps();
            });
        }

        Schema::table('bl_fixed_asset_depreciations_t', function (Blueprint $table) {
            $table->unique(['fixed_asset_id', 'accounting_period_id'], 'fixed_asset_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bl_fixed_asset_depreciations_t');
    }
};
