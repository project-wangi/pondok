<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kelas extends Model
{
    protected $fillable = ['nama_kelas'];

    public function murids()
    {
        return $this->hasMany(Murid::class);
    }

    /** @return HasOne<Guru, $this> */
    public function waliKelas(): HasOne
    {
        return $this->hasOne(Guru::class, 'kelas_id');
    }
}
