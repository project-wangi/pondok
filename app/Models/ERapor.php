<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ERapor extends Model
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
        'bobot_formatif',
        'bobot_sumatif',
        'bobot_asts',
        'bobot_asas',
        'bobot_kehadiran',
        'bobot_sikap',
        'bobot_keaktifan',
        'kkm',
        'dicatat_pada',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'dicatat_pada' => 'datetime',
            'revisi' => 'integer',
            'bobot_formatif' => 'integer',
            'bobot_sumatif' => 'integer',
            'bobot_asts' => 'integer',
            'bobot_asas' => 'integer',
            'bobot_kehadiran' => 'integer',
            'bobot_sikap' => 'integer',
            'bobot_keaktifan' => 'integer',
            'kkm' => 'integer',
        ];
    }

    /** @return HasMany<ERaporNilai, $this> */
    public function nilais(): HasMany
    {
        return $this->hasMany(ERaporNilai::class);
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
