<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aktual_produksi_bulanan', function (Blueprint $table) {
            $table->id();
            $table->string('kabupaten');
            $table->integer('tahun');
            $table->integer('bulan');
            $table->date('periode');
            $table->double('produksi_aktual');
            $table->timestamps();

            $table->unique(['kabupaten', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aktual_produksi_bulanan');
    }
};