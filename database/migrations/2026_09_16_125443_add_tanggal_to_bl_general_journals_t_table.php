<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bl_general_journals_t', function (Blueprint $table) {
            $table->date('tanggal')->nullable()->after('no_bukti');
        });

        // Set existing records' tanggal to DATE(created_at)
        DB::statement('UPDATE bl_general_journals_t SET tanggal = DATE(created_at) WHERE tanggal IS NULL');

        // Now make it not nullable if you want, but for SQLite compatibility, we can leave it nullable or just change it.
        // In Laravel 11/12, changing column nullability requires doctrine/dbal unless natively supported.
        // It's safe to leave it nullable in DB, but enforce it in Model/Form.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bl_general_journals_t', function (Blueprint $table) {
            $table->dropColumn('tanggal');
        });
    }
};
