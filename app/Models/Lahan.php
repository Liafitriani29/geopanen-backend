<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lahan extends Model
{
    use HasFactory;

    protected $table = 'lahan';

    protected $fillable = [
        'user_id',
        'nama',
        'luas',
        'kecamatan',
        'desa'
    ];

    public function panen()
    {
        return $this->hasMany(Panen::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}