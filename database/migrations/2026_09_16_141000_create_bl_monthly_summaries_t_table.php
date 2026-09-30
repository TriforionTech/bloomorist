<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bl_monthly_summaries_t', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_period_id')->unique()->constrained('bl_accounting_periods_t')->cascadeOnDelete();
            $table->decimal('net_sales', 18, 2);
            $table->decimal('cogs', 18, 2);
            $table->decimal('gross_profit', 18, 2);
            $table->decimal('operating_expenses', 18, 2);
            $table->decimal('net_income', 18, 2);
            $table->decimal('ending_cash_bank', 18, 2);
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bl_monthly_summaries_t');
    }
};
