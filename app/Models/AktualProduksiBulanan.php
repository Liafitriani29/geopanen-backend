<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AktualProduksiBulanan extends Model
{
    protected $table = 'aktual_produksi_bulanan';

    protected $fillable = [
        'kabupaten',
        'tahun',
        'bulan',
        'periode',
        'produksi_aktual',
    ];
}