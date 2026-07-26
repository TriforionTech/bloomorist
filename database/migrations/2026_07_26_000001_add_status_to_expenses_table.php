<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add status column to expenses table for ERP audit trail.
     * Existing records are set to 'posted' because they already have journals.
     */
    public function up(): void
    {
        Schema::table('bl_expenses_t', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->after('coa_kredit_id');
        });

        // All existing expenses already have journals (created in afterCreate),
        // so set them to 'posted' for backward compatibility.
        DB::table('bl_expenses_t')->update(['status' => 'posted']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bl_expenses_t', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
