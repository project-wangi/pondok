<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenPiketMurid extends Model
{
    protected $fillable = [
        'absen_piket_id',
        'murid_id',
        'nama_murid',
        'nis',
        'masuk',
        'pulang',
    ];

    /** @return BelongsTo<AbsenPiket, $this> */
    public function absenPiket(): BelongsTo
    {
        return $this->belongsTo(AbsenPiket::class);
    }

    /** @return BelongsTo<Murid, $this> */
    public function murid(): BelongsTo
    {
        return $this->belongsTo(Murid::class);
    }
}
