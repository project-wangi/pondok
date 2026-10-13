<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
