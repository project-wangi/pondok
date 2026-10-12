<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Jabatan extends Model
{
    protected $fillable = [
        'nama_jabatan',
        'gaji',
    ];

    public function gurus(): BelongsToMany
    {
        return $this->belongsToMany(Guru::class, 'guru_jabatan', 'jabatan_id', 'guru_id');
    }
}
