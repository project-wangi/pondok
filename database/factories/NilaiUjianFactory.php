<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiUjian;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<NilaiUjian> */
class NilaiUjianFactory extends Factory
{
    protected $model = NilaiUjian::class;

    /**
     * @return array{
     *     guru_id: GuruFactory,
     *     kelas_id: KelasFactory,
     *     mata_pelajaran_id: MataPelajaranFactory,
     *     pencatat_id: \Closure,
     *     revisi: int,
     *     semester: string,
     *     tahun_ajaran: string,
     *     nama_guru: \Closure,
     *     nama_kelas: \Closure,
     *     nama_mapel: \Closure,
     *     dicatat_pada: Carbon
     * }
     */
    public function definition(): array
    {
        $period = JadwalPelajaran::activePeriod();

        return [
            'guru_id' => GuruFactory::new(),
            'kelas_id' => KelasFactory::new(),
            'mata_pelajaran_id' => MataPelajaranFactory::new(),
            'pencatat_id' => fn (array $attributes): ?int => Guru::find($attributes['guru_id'])?->user_id,
            'revisi' => 1,
            'semester' => $period['semester'],
            'tahun_ajaran' => $period['tahun_ajaran'],
            'nama_guru' => fn (array $attributes): string => Guru::find($attributes['guru_id'])?->nama ?? fake()->name(),
            'nama_kelas' => fn (array $attributes): string => Kelas::find($attributes['kelas_id'])?->nama_kelas ?? fake()->word(),
            'nama_mapel' => fn (array $attributes): string => MataPelajaran::find($attributes['mata_pelajaran_id'])?->nama_mapel ?? fake()->words(2, true),
            'dicatat_pada' => now(),
        ];
    }
}
