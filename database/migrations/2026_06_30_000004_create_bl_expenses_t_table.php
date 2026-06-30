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
        Schema::create('bl_expenses_t', function (Blueprint $table) {
            $table->id();
            $table->string('keterangan');
            $table->bigInteger('nominal');
            $table->foreignId('coa_id')
                  ->constrained('bl_coa_t')
                  ->comment('Akun Beban yang dipilih');
            $table->foreignId('coa_kredit_id')
                  ->constrained('bl_coa_t')
                  ->comment('Akun Kas/Bank yang di-kredit');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bl_expenses_t');
    }
};
