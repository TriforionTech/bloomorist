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
        Schema::create('bl_accounting_periods_t', function (Blueprint $table) {
            $table->id();
            $table->string('label')->comment('Contoh: Agustus 2026');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('opening_cash_balance', 15, 2)->default(0)->comment('Saldo awal kas + bank');
            $table->decimal('closing_inventory_value', 15, 2)->nullable()->comment('Input manual hasil stock opname akhir bulan');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bl_accounting_periods_t');
    }
};
