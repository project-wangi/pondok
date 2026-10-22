<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donasi extends Model
{
    public const NOMINAL_PER_ANAK = 50000;

    protected $table = 'donasis';

    protected $fillable = [
        'guru_id',
        'tanggal',
        'jumlah_anak',
    ];

    /** @return array{tanggal: string, jumlah_anak: string} */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah_anak' => 'integer',
        ];
    }

    public function getNominalAttribute(): int
    {
        return (int) $this->jumlah_anak * self::NOMINAL_PER_ANAK;
    }

    /** @return BelongsTo<Guru, $this> */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }
}
