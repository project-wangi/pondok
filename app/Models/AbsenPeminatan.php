<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsenPeminatan extends Model
{
    /** @var array<string, string> */
    public const STATUSES = [
        'Hadir' => 'Hadir',
        'Izin' => 'Izin',
        'Sakit' => 'Sakit',
        'Alpa' => 'Alpa',
    ];

    protected $fillable = [
        'jenis_peminatan_id',
        'guru_id',
        'pencatat_id',
        'revisi',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'nama_guru',
        'nama_peminatan',
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

    /** @return HasMany<AbsenPeminatanMurid, $this> */
    public function murids(): HasMany
    {
        return $this->hasMany(AbsenPeminatanMurid::class);
    }

    /** @return BelongsTo<JenisPeminatan, $this> */
    public function jenisPeminatan(): BelongsTo
    {
        return $this->belongsTo(JenisPeminatan::class);
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
