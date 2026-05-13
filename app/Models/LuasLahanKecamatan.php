<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LuasLahanKecamatan extends Model
{
    protected $table = 'luas_lahan_kecamatan';

    protected $fillable = [
        'nama_kecamatan',
        'tahun',
        'irigasi_teknis_ha',
        'irigasi_setengah_teknis_ha',
        'irigasi_sederhana_ha',
        'tadah_hujan_ha',
        'total_luas_lahan_ha',
        'sumber_file',
    ];
}