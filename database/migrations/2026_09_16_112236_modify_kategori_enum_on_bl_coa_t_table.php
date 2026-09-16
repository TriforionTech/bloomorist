<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, change ENUM to VARCHAR
        DB::statement('ALTER TABLE bl_coa_t MODIFY kategori VARCHAR(100) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally revert to original ENUM
        DB::statement("ALTER TABLE bl_coa_t MODIFY kategori ENUM('Aset', 'Kewajiban', 'Ekuitas', 'Pendapatan', 'Beban') NOT NULL");
    }
};
