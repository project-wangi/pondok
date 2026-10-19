<?php

namespace App\Models;

use Database\Factories\PresensiMuridFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresensiMurid extends Model
{
    /** @use HasFactory<PresensiMuridFactory> */
    use HasFactory;

    public const STATUSES = [
        'Hadir' => 'Hadir',
        'Izin' => 'Izin',
        'Sakit' => 'Sakit',
        'Alpa' => 'Alpa',
    ];

    protected $fillable = [
        'sesi_mengajar_id',
        'murid_id',
        'nama_murid',
        'nis',
        'status',
        'catatan',
    ];

    /** @return BelongsTo<SesiMengajar, $this> */
    public function sesiMengajar(): BelongsTo
    {
        return $this->belongsTo(SesiMengajar::class);
    }

    /** @return BelongsTo<Murid, $this> */
    public function murid(): BelongsTo
    {
        return $this->belongsTo(Murid::class);
    }
}
