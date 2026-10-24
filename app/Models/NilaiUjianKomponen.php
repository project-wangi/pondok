<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NilaiUjianKomponen extends Model
{
    protected $fillable = [
        'nilai_ujian_id',
        'nama',
        'urutan',
    ];

    /** @return array{urutan: string} */
    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
        ];
    }

    /** @return BelongsTo<NilaiUjian, $this> */
    public function nilaiUjian(): BelongsTo
    {
        return $this->belongsTo(NilaiUjian::class);
    }

    /** @return HasMany<NilaiUjianNilai, $this> */
    public function nilais(): HasMany
    {
        return $this->hasMany(NilaiUjianNilai::class);
    }
}
