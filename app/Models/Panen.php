<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Panen extends Model
{
    protected $table = 'panen';

    protected $fillable = [
        'lahan_id',
        'tanggal',
        'hasil',
        'keterangan'
    ];

    // relasi ke lahan
    public function lahan()
    {
        return $this->belongsTo(Lahan::class);
    }
}