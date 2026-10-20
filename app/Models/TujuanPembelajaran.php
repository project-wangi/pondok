<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TujuanPembelajaran extends Model
{
    protected $fillable = [
        'lingkup_materi_id',
        'urutan',
        'teks',
    ];

    /** @return BelongsTo<LingkupMateri, $this> */
    public function lingkupMateri(): BelongsTo
    {
        return $this->belongsTo(LingkupMateri::class);
    }
}
