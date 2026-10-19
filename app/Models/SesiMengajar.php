<?php

namespace App\Models;

use Database\Factories\SesiMengajarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesiMengajar extends Model
{
    /** @use HasFactory<SesiMengajarFactory> */
    use HasFactory;

    protected $fillable = [
        'jadwal_pelajaran_id',
        'guru_id',
        'kelas_id',
        'pencatat_id',
        'revisi',
        'tanggal',
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

    /** @return HasMany<PresensiMurid, $this> */
    public function presensiMurids(): HasMany
    {
        return $this->hasMany(PresensiMurid::class);
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
}
