<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalPeminatan extends Model
{
    protected $fillable = [
        'hari',
        'jam_mulai',
        'jam_selesai',
        'guru_id',
        'jenis_peminatan_id',
    ];

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }

    public function jenisPeminatan()
    {
        return $this->belongsTo(JenisPeminatan::class);
    }
}
