<?php

namespace App\Models;

use Database\Factories\MataPelajaranFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataPelajaran extends Model
{
    /** @use HasFactory<MataPelajaranFactory> */
    use HasFactory;

    protected $fillable = [
        'kode_mapel',
        'nama_mapel',
        'unit',
    ];

    /** @return HasMany<ERapor, $this> */
    public function erapors(): HasMany
    {
        return $this->hasMany(ERapor::class, 'mata_pelajaran_id');
    }

    /**
     * Mata pelajaran untuk satu unit, termasuk yang berlaku untuk semua unit
     * (kolom unit kosong).
     *
     * @param  Builder<MataPelajaran>  $query
     * @return Builder<MataPelajaran>
     */
    public function scopeForUnit(Builder $query, ?string $unit): Builder
    {
        if (blank($unit)) {
            return $query;
        }

        return $query->where(fn (Builder $query): Builder => $query->where('unit', $unit)->orWhereNull('unit'));
    }
}
