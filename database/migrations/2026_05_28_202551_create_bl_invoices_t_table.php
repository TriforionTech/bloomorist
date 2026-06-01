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
        Schema::create('bl_invoices_t', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->enum('customer_type', ['member', 'non_member'])->default('member');
            $table->unsignedBigInteger('customer_id')->nullable(); // can link to customers
            $table->unsignedBigInteger('membership_id')->nullable();
            
            // Temporary form states for discount
            $table->string('discount_mode')->nullable(); // none, global, per_item
            $table->boolean('discount_mode_member')->default(false);
            $table->decimal('custom_discount', 5, 2)->default(0); // global discount
            
            // Add ons
            $table->decimal('ongkir', 15, 2)->default(0);
            $table->boolean('use_box')->default(false);
            $table->boolean('use_wrapping')->default(false);
            
            // Summary
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            
            // Invoice Details
            $table->date('issued_date')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'refunded', 'cancelled'])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bl_invoices_t');
    }
};
