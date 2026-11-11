<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role;

class Guru extends Model
{
    protected $fillable = [
        'user_id',
        'kelas_id',
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

    /**
     * Setiap akun guru otomatis memakai role guru agar hak akses halamannya
     * dapat diatur dari menu Role & Permission tanpa menyentuh kode.
     */
    protected static function booted(): void
    {
        static::saved(function (Guru $guru): void {
            $user = $guru->user()->first();

            if ($user !== null && ! $user->hasRole('guru')) {
                $user->assignRole(Role::findOrCreate('guru', 'web'));
            }
        });
    }

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

    /** @return BelongsTo<Kelas, $this> */
    public function kelasWali(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }
}
