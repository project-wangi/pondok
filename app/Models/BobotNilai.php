<?php

namespace App\Models;

use Database\Factories\BobotNilaiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bobot nilai rapor per mata pelajaran dan periode, diatur oleh role kurikulum.
 *
 * @property int $mata_pelajaran_id
 * @property string $semester
 * @property string $tahun_ajaran
 * @property int $bobot_formatif
 * @property int $bobot_sumatif
 * @property int $bobot_asts
 * @property int $bobot_asas
 * @property int $bobot_kehadiran
 * @property int $bobot_sikap
 * @property int $bobot_keaktifan
 */
class BobotNilai extends Model
{
    /** @use HasFactory<BobotNilaiFactory> */
    use HasFactory;

    /** @var array<string, int> */
    public const BAWAAN = [
        'formatif' => 10,
        'sumatif' => 15,
        'asts' => 20,
        'asas' => 35,
        'kehadiran' => 5,
        'sikap' => 5,
        'keaktifan' => 10,
    ];

    /** @var list<string> */
    public const KOMPONEN = [
        'formatif' => 'Nilai Formatif',
        'sumatif' => 'Sumatif Lingkup Materi',
        'asts' => 'Sumatif Tengah Semester (ASTS)',
        'asas' => 'Sumatif Akhir Semester (ASAS)',
        'kehadiran' => 'Nilai Kehadiran',
        'sikap' => 'Nilai Sikap',
        'keaktifan' => 'Nilai Keaktifan',
    ];

    protected $fillable = [
        'mata_pelajaran_id',
        'semester',
        'tahun_ajaran',
        'bobot_formatif',
        'bobot_sumatif',
        'bobot_asts',
        'bobot_asas',
        'bobot_kehadiran',
        'bobot_sikap',
        'bobot_keaktifan',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bobot_formatif' => 'integer',
            'bobot_sumatif' => 'integer',
            'bobot_asts' => 'integer',
            'bobot_asas' => 'integer',
            'bobot_kehadiran' => 'integer',
            'bobot_sikap' => 'integer',
            'bobot_keaktifan' => 'integer',
        ];
    }

    /**
     * Bobot efektif untuk satu mata pelajaran dan periode.
     *
     * @return array<string, int>
     */
    public static function untuk(int $mataPelajaranId, string $semester, string $tahunAjaran): array
    {
        $record = static::query()
            ->where('mata_pelajaran_id', $mataPelajaranId)
            ->where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->first();

        if ($record === null) {
            return self::BAWAAN;
        }

        return [
            'formatif' => $record->bobot_formatif,
            'sumatif' => $record->bobot_sumatif,
            'asts' => $record->bobot_asts,
            'asas' => $record->bobot_asas,
            'kehadiran' => $record->bobot_kehadiran,
            'sikap' => $record->bobot_sikap,
            'keaktifan' => $record->bobot_keaktifan,
        ];
    }

    /**
     * Kolom bobot pada tabel e_rapors agar snapshot rapor tetap sinkron.
     *
     * @param  array<string, int>  $bobot
     * @return array<string, int>
     */
    public static function kolomRapor(array $bobot): array
    {
        return [
            'bobot_formatif' => $bobot['formatif'],
            'bobot_sumatif' => $bobot['sumatif'],
            'bobot_asts' => $bobot['asts'],
            'bobot_asas' => $bobot['asas'],
            'bobot_kehadiran' => $bobot['kehadiran'],
            'bobot_sikap' => $bobot['sikap'],
            'bobot_keaktifan' => $bobot['keaktifan'],
        ];
    }

    /** @return BelongsTo<MataPelajaran, $this> */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }
}
