<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuaca', function (Blueprint $table) {
            if (!Schema::hasColumn('cuaca', 'kategori')) {
                $table->string('kategori')->nullable()->after('kondisi');
            }

            if (!Schema::hasColumn('cuaca', 'rekomendasi')) {
                $table->text('rekomendasi')->nullable()->after('kategori');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cuaca', function (Blueprint $table) {
            if (Schema::hasColumn('cuaca', 'rekomendasi')) {
                $table->dropColumn('rekomendasi');
            }

            if (Schema::hasColumn('cuaca', 'kategori')) {
                $table->dropColumn('kategori');
            }
        });
    }
};