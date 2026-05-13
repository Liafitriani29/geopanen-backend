<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatatanPenyuluh extends Model
{
    protected $table = 'catatan_penyuluh';

    protected $fillable = [
        'user_id',
        'nama_penyuluh',
        'kabupaten',
        'tahun',
        'bulan',
        'periode',
        'catatan',
    ];
}