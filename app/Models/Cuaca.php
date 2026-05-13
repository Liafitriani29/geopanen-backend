<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuaca extends Model
{
    protected $table = 'cuaca';

    protected $fillable = [
        'tanggal',
        'kecamatan',
        'desa',
        'suhu',
        'kelembaban',
        'kondisi',
        'kategori',
        'rekomendasi',
    ];
}