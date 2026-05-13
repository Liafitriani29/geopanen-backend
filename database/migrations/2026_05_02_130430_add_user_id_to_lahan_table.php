<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom user_id hanya ditambahkan jika memang belum ada
        if (!Schema::hasColumn('lahan', 'user_id')) {
            Schema::table('lahan', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        // Jangan hapus user_id otomatis agar data relasi lahan-petani tidak hilang
    }
};