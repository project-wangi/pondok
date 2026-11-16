<?php

namespace App\Models;

use Database\Factories\AbsenGuruFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Absensi harian guru: jam masuk dan jam pulang pada satu tanggal.
 *
 * Catatan ini ikut dihitung sebagai hari hadir pada uang transport di menu
 * Gaji Guru, lihat GajiGuru::attendanceDates().
 */
class AbsenGuru extends Model
{
    /** @use HasFactory<AbsenGuruFactory> */
    use HasFactory;

    protected $fillable = [
        'guru_id',
        'user_id',
        'nama_guru',
        'tanggal',
        'masuk_pada',
        'pulang_pada',
    ];

    /** @return array{tanggal: string, masuk_pada: string, pulang_pada: string} */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'masuk_pada' => 'datetime',
            'pulang_pada' => 'datetime',
        ];
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
