<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('luas_lahan_kecamatan', function (Blueprint $table) {
            $table->id();

            $table->string('nama_kecamatan');
            $table->year('tahun');

            $table->decimal('irigasi_teknis_ha', 12, 2)->nullable();
            $table->decimal('irigasi_setengah_teknis_ha', 12, 2)->nullable();
            $table->decimal('irigasi_sederhana_ha', 12, 2)->nullable();
            $table->decimal('tadah_hujan_ha', 12, 2)->nullable();
            $table->decimal('total_luas_lahan_ha', 12, 2)->nullable();

            $table->string('sumber_file')->nullable();

            $table->timestamps();

            $table->unique(['nama_kecamatan', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('luas_lahan_kecamatan');
    }
};