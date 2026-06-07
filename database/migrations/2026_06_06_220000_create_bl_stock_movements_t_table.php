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
        Schema::create('bl_stock_movements_t', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('bl_products_t')->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'sale', 'return', 'opname']);
            $table->unsignedInteger('quantity');
            $table->string('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('product_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bl_stock_movements_t');
    }
};
