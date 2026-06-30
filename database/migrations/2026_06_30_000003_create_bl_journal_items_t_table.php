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
        Schema::create('bl_journal_items_t', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')
                  ->constrained('bl_general_journals_t')
                  ->cascadeOnDelete();
            $table->foreignId('coa_id')
                  ->constrained('bl_coa_t');
            $table->string('kode_coa'); // denormalized from bl_coa_t.kode_akun
            $table->bigInteger('debit')->default(0);
            $table->bigInteger('kredit')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bl_journal_items_t');
    }
};
