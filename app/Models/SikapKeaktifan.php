<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SikapKeaktifan extends Model
{
    /**
     * Kategori huruf dan konversi angkanya.
     *
     * @var array<string, int>
     */
    public const KATEGORI = [
        'A' => 100,
        'B' => 90,
        'C' => 80,
        'D' => 60,
        'E' => 40,
    ];

    /**
     * Label kategori untuk nilai sikap.
     *
     * @var array<string, string>
     */
    public const LABEL_SIKAP = [
        'A' => 'Sangat Baik',
        'B' => 'Baik',
        'C' => 'Cukup',
        'D' => 'Perlu Bimbingan',
        'E' => 'Perlu Pendampingan Intensif',
    ];

    /**
     * Label kategori untuk nilai keaktifan.
     *
     * @var array<string, string>
     */
    public const LABEL_KEAKTIFAN = [
        'A' => 'Sangat Aktif',
        'B' => 'Aktif',
        'C' => 'Cukup Aktif',
        'D' => 'Kurang Aktif',
        'E' => 'Perlu Pendampingan Intensif',
    ];

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

    public static function angkaUntuk(?string $kategori): ?int
    {
        if ($kategori === null || $kategori === '') {
            return null;
        }

        return self::KATEGORI[mb_strtoupper($kategori)] ?? null;
    }

    /** @return HasMany<SikapKeaktifanNilai, $this> */
    public function nilais(): HasMany
    {
        return $this->hasMany(SikapKeaktifanNilai::class);
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
