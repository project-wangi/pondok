<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class NilaiUjian extends Model
{
    protected $fillable = [
        'guru_id',
        'kelas_id',
        'mata_pelajaran_id',
        'pencatat_id',
        'revisi',
        'semester',
        'tahun_ajaran',
        'nama_guru',
        'nama_kelas',
        'nama_mapel',
        'dicatat_pada',
    ];

    /** @return array{dicatat_pada: string, revisi: string} */
    protected function casts(): array
    {
        return [
            'dicatat_pada' => 'datetime',
            'revisi' => 'integer',
        ];
    }

    /** @return HasMany<NilaiUjianKomponen, $this> */
    public function komponens(): HasMany
    {
        return $this->hasMany(NilaiUjianKomponen::class)->orderBy('urutan');
    }

    /** @return HasManyThrough<NilaiUjianNilai, NilaiUjianKomponen, $this> */
    public function nilais(): HasManyThrough
    {
        return $this->hasManyThrough(NilaiUjianNilai::class, NilaiUjianKomponen::class);
    }

    /** @return BelongsTo<Guru, $this> */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
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
