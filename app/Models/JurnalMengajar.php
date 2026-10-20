<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JurnalMengajar extends Model
{
    protected $fillable = [
        'jadwal_pelajaran_id',
        'guru_id',
        'kelas_id',
        'mata_pelajaran_id',
        'pencatat_id',
        'revisi',
        'tanggal',
        'semester',
        'tahun_ajaran',
        'nama_guru',
        'nama_kelas',
        'nama_mapel',
        'jam_mulai',
        'jam_selesai',
        'dicatat_pada',
    ];

    /** @return array{tanggal: string, dicatat_pada: string, revisi: string} */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'dicatat_pada' => 'datetime',
            'revisi' => 'integer',
        ];
    }

    /** @return HasMany<JurnalMengajarBaris, $this> */
    public function baris(): HasMany
    {
        return $this->hasMany(JurnalMengajarBaris::class)->orderBy('urutan');
    }

    /** @return BelongsTo<JadwalPelajaran, $this> */
    public function jadwalPelajaran(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class);
    }

    /** @return BelongsTo<Guru, $this> */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pencatat_id');
    }

    /** @return BelongsTo<Kelas, $this> */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /** @return BelongsTo<MataPelajaran, $this> */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }
}
