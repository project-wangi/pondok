<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsenPiket extends Model
{
    /** @var array<string, string> */
    public const STATUS_MASUK = [
        'Hadir' => 'Hadir',
        'Terlambat' => 'Terlambat',
        'Izin' => 'Izin',
        'Sakit' => 'Sakit',
        'Alpa' => 'Alpa',
    ];

    /** @var array<string, string> */
    public const STATUS_PULANG = [
        'Pulang' => 'Pulang',
        'Izin' => 'Izin',
        'Sakit' => 'Sakit',
        'Alpa' => 'Alpa',
    ];

    protected $fillable = [
        'kelas_id',
        'guru_id',
        'pencatat_id',
        'revisi',
        'tanggal',
        'nama_guru',
        'nama_kelas',
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

    /** @return HasMany<AbsenPiketMurid, $this> */
    public function murids(): HasMany
    {
        return $this->hasMany(AbsenPiketMurid::class);
    }

    /** @return BelongsTo<Kelas, $this> */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
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
}
