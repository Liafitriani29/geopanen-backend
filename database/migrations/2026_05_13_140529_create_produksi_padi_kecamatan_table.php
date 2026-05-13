<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produksi_padi_kecamatan', function (Blueprint $table) {
            $table->id();

            $table->string('nama_kecamatan');
            $table->year('tahun');

            $table->decimal('luas_panen_sawah_ha', 12, 2)->nullable();
            $table->decimal('produktivitas_sawah_kw_ha', 12, 2)->nullable();
            $table->decimal('produksi_sawah_ton', 12, 2)->nullable();

            $table->decimal('luas_panen_gogo_ha', 12, 2)->nullable();
            $table->decimal('produktivitas_gogo_kw_ha', 12, 2)->nullable();
            $table->decimal('produksi_gogo_ton', 12, 2)->nullable();

            $table->decimal('total_produksi_ton', 12, 2)->nullable();
            $table->string('sumber_file')->nullable();

            $table->timestamps();

            $table->unique(['nama_kecamatan', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produksi_padi_kecamatan');
    }
};