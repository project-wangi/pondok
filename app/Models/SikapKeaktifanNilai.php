<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SikapKeaktifanNilai extends Model
{
    protected $fillable = [
        'sikap_keaktifan_id',
        'murid_id',
        'nama_murid',
        'nis',
        'jenis_kelamin',
        'sikap',
        'keaktifan',
    ];

    public function sikapAngka(): ?int
    {
        return SikapKeaktifan::angkaUntuk($this->sikap);
    }

    public function keaktifanAngka(): ?int
    {
        return SikapKeaktifan::angkaUntuk($this->keaktifan);
    }

    /** @return BelongsTo<SikapKeaktifan, $this> */
    public function sikapKeaktifan(): BelongsTo
    {
        return $this->belongsTo(SikapKeaktifan::class);
    }

    /** @return BelongsTo<Murid, $this> */
    public function murid(): BelongsTo
    {
        return $this->belongsTo(Murid::class);
    }
}
