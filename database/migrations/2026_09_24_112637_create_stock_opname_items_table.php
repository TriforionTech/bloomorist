<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bl_stock_opname_items_t', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('bl_stock_opnames_t')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('bl_products_t')->cascadeOnDelete();
            $table->integer('system_qty');
            $table->integer('physical_qty');
            $table->integer('variance'); // physical_qty - system_qty
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_value', 15, 2); // variance * unit_cost
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bl_stock_opname_items_t');
    }
};
