<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsesmenFormatifNilai extends Model
{
    protected $fillable = [
        'asesmen_formatif_id',
        'murid_id',
        'lingkup_materi_id',
        'tujuan_pembelajaran_id',
        'nama_murid',
        'nis',
        'jenis_kelamin',
        'lingkup_materi',
        'tujuan_pembelajaran',
        'nilai',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'integer',
        ];
    }

    /** @return BelongsTo<AsesmenFormatif, $this> */
    public function asesmenFormatif(): BelongsTo
    {
        return $this->belongsTo(AsesmenFormatif::class);
    }

    /** @return BelongsTo<Murid, $this> */
    public function murid(): BelongsTo
    {
        return $this->belongsTo(Murid::class);
    }

    /** @return BelongsTo<LingkupMateri, $this> */
    public function lingkupMateri(): BelongsTo
    {
        return $this->belongsTo(LingkupMateri::class);
    }

    /** @return BelongsTo<TujuanPembelajaran, $this> */
    public function tujuanPembelajaran(): BelongsTo
    {
        return $this->belongsTo(TujuanPembelajaran::class);
    }
}
