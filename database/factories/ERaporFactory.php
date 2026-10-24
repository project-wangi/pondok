<?php

namespace Database\Factories;

use App\Models\ERapor;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<ERapor> */
class ERaporFactory extends Factory
{
    protected $model = ERapor::class;

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
     *     bobot_formatif: int,
     *     bobot_sumatif: int,
     *     bobot_asts: int,
     *     bobot_asas: int,
     *     bobot_kehadiran: int,
     *     bobot_sikap: int,
     *     bobot_keaktifan: int,
     *     kkm: int,
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
            'bobot_formatif' => 10,
            'bobot_sumatif' => 15,
            'bobot_asts' => 20,
            'bobot_asas' => 35,
            'bobot_kehadiran' => 5,
            'bobot_sikap' => 5,
            'bobot_keaktifan' => 10,
            'kkm' => 75,
            'dicatat_pada' => now(),
        ];
    }
}
