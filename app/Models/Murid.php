<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Murid extends Model
{
    protected $fillable = [
        'kelas_id',
        'foto',
        'nama',
        'nisn',
        'nis',
        'nik',
        'no_kk',
        'email',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'nama_ayah',
        'nik_ayah',
        'pekerjaan_ayah',
        'nama_ibu',
        'nik_ibu',
        'pekerjaan_ibu',
        'penghasilan_ayah',
        'penghasilan_ibu',
        'nohp_siswa',
        'nohp_ayah',
        'nohp_ibu',
        'ijazah',
        'kk',
        'kip',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    /** @return BelongsToMany<JenisPeminatan, $this> */
    public function jenisPeminatans(): BelongsToMany
    {
        return $this->belongsToMany(JenisPeminatan::class, 'jenis_peminatan_murid');
    }

    public function jenisKelaminKode(): string
    {
        return match (mb_strtolower(trim((string) $this->jenis_kelamin))) {
            'laki-laki', 'laki laki', 'l', 'lk' => 'L',
            'perempuan', 'p', 'pr' => 'P',
            default => '-',
        };
    }
}
