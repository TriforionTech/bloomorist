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
        Schema::create('bl_general_journals_t', function (Blueprint $table) {
            $table->id();
            $table->string('no_bukti')->unique();
            $table->string('keterangan');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('source_type')->nullable(); // EXPENSE, INVOICE, MANUAL
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bl_general_journals_t');
    }
};
