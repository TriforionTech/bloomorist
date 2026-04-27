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
        Schema::create('bl_member_users_t', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('alias')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('alamat')->nullable();
            $table->string('kota')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('negara')->nullable();
            $table->string('nomor_hp')->nullable();
            $table->integer('membership_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();
                });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bl_member_users_t');
    }
};
