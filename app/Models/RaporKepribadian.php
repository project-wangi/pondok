<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaporKepribadian extends Model
{
    /**
     * Aspek kepribadian yang dinilai wali kelas.
     *
     * @var array<string, string>
     */
    public const ASPEK = [
        'kelakuan' => 'Kelakuan',
        'kerajinan' => 'Kerajinan',
        'kebersihan' => 'Kebersihan',
        'kerapian' => 'Kerapian',
        'kedisiplinan' => 'Kedisiplinan',
    ];

    protected $fillable = [
        'murid_id',
        'semester',
        'tahun_ajaran',
        'kelakuan',
        'kerajinan',
        'kebersihan',
        'kerapian',
        'kedisiplinan',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kelakuan' => 'integer',
            'kerajinan' => 'integer',
            'kebersihan' => 'integer',
            'kerapian' => 'integer',
            'kedisiplinan' => 'integer',
        ];
    }

    /**
     * Konversi angka kepribadian dan mata pelajaran menjadi huruf.
     */
    public static function hurufUntuk(?int $angka): ?string
    {
        if ($angka === null) {
            return null;
        }

        return match (true) {
            $angka >= 90 => 'A',
            $angka >= 80 => 'B',
            $angka >= 70 => 'C',
            $angka >= 60 => 'D',
            default => 'E',
        };
    }

    /** @return BelongsTo<Murid, $this> */
    public function murid(): BelongsTo
    {
        return $this->belongsTo(Murid::class);
    }
}
