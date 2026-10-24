<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ERaporNilai extends Model
{
    protected $fillable = [
        'e_rapor_id',
        'murid_id',
        'nama_murid',
        'nis',
        'asts',
        'asas',
    ];

    /** @return array{asts: string, asas: string} */
    protected function casts(): array
    {
        return [
            'asts' => 'integer',
            'asas' => 'integer',
        ];
    }

    /** @return BelongsTo<ERapor, $this> */
    public function eRapor(): BelongsTo
    {
        return $this->belongsTo(ERapor::class);
    }

    /** @return BelongsTo<Murid, $this> */
    public function murid(): BelongsTo
    {
        return $this->belongsTo(Murid::class);
    }
}
