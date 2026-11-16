<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Jabatan extends Model
{
    protected $fillable = [
        'nama_jabatan',
        'gaji',
    ];

    public function gurus(): BelongsToMany
    {
        return $this->belongsToMany(Guru::class, 'guru_jabatan', 'jabatan_id', 'guru_id');
    }

    /**
     * Nama guru pertama yang memegang jabatan dengan nama tersebut.
     * Pencocokan nama jabatan tidak peka huruf besar/kecil.
     */
    public static function namaPemegang(string $namaJabatan): ?string
    {
        $jabatan = static::query()
            ->with(['gurus' => fn (BelongsToMany $query) => $query->with('user:id,name')->orderBy('gurus.id')])
            ->get(['id', 'nama_jabatan'])
            ->first(
                fn (Jabatan $item): bool => mb_strtolower((string) $item->nama_jabatan) === mb_strtolower($namaJabatan),
            );

        return $jabatan?->gurus->first()?->nama;
    }
}
