<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kelas extends Model
{
    public const UNIT_MTS = 'MTs';

    public const UNIT_MA = 'MA';

    /** @var array<string, string> */
    public const UNITS = [
        self::UNIT_MTS => 'MTs',
        self::UNIT_MA => 'MA',
    ];

    /** @var array<string, int> */
    private const TINGKAT_ROMAWI = [
        'VII' => 7,
        'VIII' => 8,
        'IX' => 9,
        'X' => 10,
        'XI' => 11,
        'XII' => 12,
    ];

    protected $fillable = ['nama_kelas'];

    /**
     * Tingkat kelas dari nama kelas, mendukung angka Romawi (VII, X) maupun
     * angka biasa (7, 10) beserta akhiran seperti "VII A".
     */
    public function tingkat(): ?int
    {
        $nama = mb_strtoupper(trim((string) $this->nama_kelas));

        if ($nama === '') {
            return null;
        }

        foreach (preg_split('/[\s\-_.\/]+/', $nama) ?: [] as $token) {
            if (isset(self::TINGKAT_ROMAWI[$token])) {
                return self::TINGKAT_ROMAWI[$token];
            }

            if (ctype_digit($token) && (int) $token >= 1 && (int) $token <= 12) {
                return (int) $token;
            }
        }

        return null;
    }

    /** Unit madrasah: MTs untuk kelas 7-9, MA untuk kelas 10-12. */
    public function getUnitAttribute(): ?string
    {
        return match ($this->tingkat()) {
            7, 8, 9 => self::UNIT_MTS,
            10, 11, 12 => self::UNIT_MA,
            default => null,
        };
    }

    public function murids()
    {
        return $this->hasMany(Murid::class);
    }

    /** @return HasOne<Guru, $this> */
    public function waliKelas(): HasOne
    {
        return $this->hasOne(Guru::class, 'kelas_id');
    }
}
