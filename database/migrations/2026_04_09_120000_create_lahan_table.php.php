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
    Schema::create('lahan', function (Blueprint $table) {
        $table->id();
        $table->string('nama');
        $table->float('luas');
        $table->string('kecamatan');
        $table->string('desa');
        $table->timestamps();
    });
}
};
