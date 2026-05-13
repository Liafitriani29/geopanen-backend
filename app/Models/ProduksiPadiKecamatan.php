<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiPadiKecamatan extends Model
{
    protected $table = 'produksi_padi_kecamatan';

    protected $fillable = [
        'nama_kecamatan',
        'tahun',
        'luas_panen_sawah_ha',
        'produktivitas_sawah_kw_ha',
        'produksi_sawah_ton',
        'luas_panen_gogo_ha',
        'produktivitas_gogo_kw_ha',
        'produksi_gogo_ton',
        'total_produksi_ton',
        'sumber_file',
    ];
}