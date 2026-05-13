<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuaca', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('kecamatan');
            $table->string('desa')->nullable();
            $table->decimal('suhu', 5, 2);
            $table->decimal('kelembaban', 5, 2)->nullable();
            $table->string('kondisi')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuaca');
    }
};