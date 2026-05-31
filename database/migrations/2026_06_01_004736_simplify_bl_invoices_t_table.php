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
            $table->dropColumn([
                'customer_name',
                'customer_alias',
                'customer_address',
                'customer_city',
                'customer_province',
                'customer_country',
                'customer_email',
                'customer_phone',
                'membership_id',
                'box_qty',
                'box_unit_price',
                'box_fee',
                'wrapping_qty',
                'wrap_unit_price',
                'wrapping_fee',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bl_invoices_t', function (Blueprint $table) {
            $table->string('customer_name')->nullable();
            $table->string('customer_alias')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_province')->nullable();
            $table->string('customer_country')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->unsignedBigInteger('membership_id')->nullable();
            
            $table->integer('box_qty')->nullable();
            $table->decimal('box_unit_price', 15, 2)->default(0);
            $table->decimal('box_fee', 15, 2)->default(0);
            $table->integer('wrapping_qty')->nullable();
            $table->decimal('wrap_unit_price', 15, 2)->default(0);
            $table->decimal('wrapping_fee', 15, 2)->default(0);
        });
    }
};
