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
        Schema::create('bl_stock_transfers_t', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('source_product_id')->constrained('bl_products_t')->cascadeOnDelete();
            $table->foreignId('target_product_id')->constrained('bl_products_t')->cascadeOnDelete();
            $table->integer('quantity');
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bl_stock_transfers_t');
    }
};
