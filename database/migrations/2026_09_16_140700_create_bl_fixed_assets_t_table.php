<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bl_fixed_assets_t', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('purchase_date');
            $table->decimal('acquisition_cost', 18, 2);
            $table->unsignedSmallInteger('useful_life_months')->default(48);
            $table->foreignId('asset_account_id')->constrained('bl_coa_t');
            $table->foreignId('expense_account_id')->constrained('bl_coa_t');
            $table->foreignId('accum_dep_account_id')->constrained('bl_coa_t');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bl_fixed_assets_t');
    }
};
