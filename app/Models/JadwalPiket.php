<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalPiket extends Model
{
    protected $fillable = [
        'hari',
        'guru_id',
        'ket',
    ];

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }
}
