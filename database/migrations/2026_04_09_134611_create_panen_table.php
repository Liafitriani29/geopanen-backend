<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panen', function (Blueprint $table) {
            $table->id();

            // 🔥 FIX DI SINI
            $table->foreignId('lahan_id')
                  ->constrained('lahan') // ⬅️ WAJIB (bukan default)
                  ->onDelete('cascade');

            $table->date('tanggal');
            $table->float('hasil');
            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panen');
    }
};