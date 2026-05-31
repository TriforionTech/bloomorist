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
        Schema::create('bl_invoice_items_t', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('bl_invoices_t')->onDelete('cascade');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('normal_price', 15, 2)->default(0);
            $table->decimal('item_discount', 5, 2)->default(0); // diskon persen
            $table->decimal('discount_price', 15, 2)->default(0); // hasil setelah diskon
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bl_invoice_items_t');
    }
};
