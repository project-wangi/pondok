<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisPeminatan extends Model
{
    protected $fillable = [
        'nama_peminatan',
        'nominal',
    ];

    public function jadwalPeminatans(): HasMany
    {
        return $this->hasMany(JadwalPeminatan::class);
    }
}
