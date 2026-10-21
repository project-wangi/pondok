<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenPeminatanMurid extends Model
{
    protected $fillable = [
        'absen_peminatan_id',
        'murid_id',
        'nama_murid',
        'nis',
        'nama_kelas',
        'status',
    ];

    /** @return BelongsTo<AbsenPeminatan, $this> */
    public function absenPeminatan(): BelongsTo
    {
        return $this->belongsTo(AbsenPeminatan::class);
    }

    /** @return BelongsTo<Murid, $this> */
    public function murid(): BelongsTo
    {
        return $this->belongsTo(Murid::class);
    }
}
