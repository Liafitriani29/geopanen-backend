<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kecamatan extends Model
{
    protected $table = 'kecamatans';

    protected $fillable = [
        'nama_kecamatan',
        'geojson',
        'latitude',
        'longitude',
    ];
}