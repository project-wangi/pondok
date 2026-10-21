<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LingkupMateri extends Model
{
    protected $fillable = [
        'guru_id',
        'kelas_id',
        'mata_pelajaran_id',
        'semester',
        'tahun_ajaran',
        'urutan',
        'materi',
    ];

    /** @return HasMany<TujuanPembelajaran, $this> */
    public function tujuanPembelajarans(): HasMany
    {
        return $this->hasMany(TujuanPembelajaran::class)->orderBy('urutan');
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
