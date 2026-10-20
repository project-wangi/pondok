<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JadwalPelajaran extends Model
{
    public const SEMESTERS = ['Ganjil' => 'Ganjil', 'Genap' => 'Genap'];

    protected $fillable = [
        'hari',
        'guru_id',
        'mata_pelajaran',
        'jam_mulai',
        'jam_selesai',
        'kelas_id',
        'semester',
        'tahun_ajaran',
    ];

    /**
     * Periode akademik berjalan: Juli-Desember Ganjil, Januari-Juni Genap.
     *
     * @return array{semester: string, tahun_ajaran: string}
     */
    public static function activePeriod(?CarbonInterface $date = null): array
    {
        $date ??= now();
        $startYear = $date->month >= 7 ? $date->year : $date->year - 1;

        return [
            'semester' => $date->month >= 7 ? 'Ganjil' : 'Genap',
            'tahun_ajaran' => $startYear.'/'.($startYear + 1),
        ];
    }

    public function scopeForPeriod(Builder $query, string $semester, string $tahunAjaran): Builder
    {
        return $query->where('semester', $semester)->where('tahun_ajaran', $tahunAjaran);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class, 'guru_id');
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }
}
