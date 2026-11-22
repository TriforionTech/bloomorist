<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bl_report_line_templates_t', function (Blueprint $table) {
            $table->id();
            $table->string('report_type');
            $table->string('line_key');
            $table->string('label');
            $table->json('account_codes')->nullable();
            $table->smallInteger('sign')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_subtotal')->default(false);
            $table->string('subtotal_formula')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['report_type', 'line_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bl_report_line_templates_t');
    }
};
