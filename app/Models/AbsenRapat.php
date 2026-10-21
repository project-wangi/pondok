<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenRapat extends Model
{
    public const FOTO_DISK = 'r2';

    protected $fillable = [
        'jadwal_rapat_id',
        'guru_id',
        'user_id',
        'nama_guru',
        'agenda',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'foto',
        'dicatat_pada',
    ];

    /** @return array{tanggal: string, dicatat_pada: string} */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'dicatat_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<JadwalRapat, $this> */
    public function jadwalRapat(): BelongsTo
    {
        return $this->belongsTo(JadwalRapat::class);
    }

    /** @return BelongsTo<Guru, $this> */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
