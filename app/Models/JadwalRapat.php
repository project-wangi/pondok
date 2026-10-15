<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalRapat extends Model
{
    protected $fillable = ['tanggal', 'hari', 'jam_mulai', 'jam_selesai', 'agenda'];

    public function gurus()
    {
        return $this->belongsToMany(Guru::class, 'jadwal_rapat_gurus', 'jadwal_rapat_id', 'guru_id');
    }
}
