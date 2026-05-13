<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catatan_penyuluh', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nama_penyuluh')->nullable();

            $table->string('kabupaten')->default('Sukoharjo');
            $table->integer('tahun');
            $table->integer('bulan');
            $table->date('periode');

            $table->text('catatan');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_penyuluh');
    }
};