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

    protected $fillable = ['nama_kelas', 'unit'];

    /**
     * Tingkat kelas dari nama kelas, mendukung angka Romawi (VII, X) maupun
     * angka biasa (7, 10) beserta akhiran seperti "VII A".
     */
    public static function tingkatDariNama(string $nama): ?int
    {
        $nama = mb_strtoupper(trim($nama));

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

    public function tingkat(): ?int
    {
        return self::tingkatDariNama((string) $this->nama_kelas);
    }

    /** Unit madrasah dari nama kelas: MTs untuk 7-9, MA untuk 10-12. */
    public static function unitUntukNama(string $nama): ?string
    {
        return match (self::tingkatDariNama($nama)) {
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
