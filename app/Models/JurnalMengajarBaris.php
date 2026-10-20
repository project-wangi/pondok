<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JurnalMengajarBaris extends Model
{
    protected $fillable = [
        'jurnal_mengajar_id',
        'lingkup_materi_id',
        'tujuan_pembelajaran_id',
        'lingkup_materi',
        'tujuan_pembelajaran',
        'kegiatan',
        'catatan',
        'tindak_lanjut',
        'urutan',
    ];

    /** @return BelongsTo<JurnalMengajar, $this> */
    public function jurnalMengajar(): BelongsTo
    {
        return $this->belongsTo(JurnalMengajar::class);
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
