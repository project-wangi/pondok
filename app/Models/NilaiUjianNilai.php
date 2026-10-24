<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiUjianNilai extends Model
{
    protected $fillable = [
        'nilai_ujian_komponen_id',
        'murid_id',
        'nama_murid',
        'nis',
        'nilai',
        'catatan',
    ];

    /** @return array{nilai: string} */
    protected function casts(): array
    {
        return [
            'nilai' => 'integer',
        ];
    }

    /** @return BelongsTo<NilaiUjianKomponen, $this> */
    public function komponen(): BelongsTo
    {
        return $this->belongsTo(NilaiUjianKomponen::class, 'nilai_ujian_komponen_id');
    }

    /** @return BelongsTo<Murid, $this> */
    public function murid(): BelongsTo
    {
        return $this->belongsTo(Murid::class);
    }
}
