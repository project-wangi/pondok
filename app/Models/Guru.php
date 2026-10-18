<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guru extends Model
{

    protected $fillable = [
        'user_id',
        'foto',
        'agama',
        'tempat_lahir',
        'tanggal_lahir',
        'alamat',
        'jarak_rumah',
        'jenis_kelamin',
        'ijazah',
        'ktp',
        'kk',
    ];

    protected $appends = ['nama'];

    public function getNamaAttribute()
    {
        return $this->user->name;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function jabatans(): BelongsToMany
    {
        return $this->belongsToMany(Jabatan::class, 'guru_jabatan', 'guru_id', 'jabatan_id');
    }

}
