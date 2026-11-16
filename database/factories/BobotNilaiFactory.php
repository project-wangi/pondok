<?php

namespace Database\Factories;

use App\Models\BobotNilai;
use App\Models\JadwalPelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BobotNilai> */
class BobotNilaiFactory extends Factory
{
    protected $model = BobotNilai::class;

    /**
     * @return array{
     *     mata_pelajaran_id: MataPelajaranFactory,
     *     semester: string,
     *     tahun_ajaran: string,
     *     bobot_formatif: int,
     *     bobot_sumatif: int,
     *     bobot_asts: int,
     *     bobot_asas: int,
     *     bobot_kehadiran: int,
     *     bobot_sikap: int,
     *     bobot_keaktifan: int
     * }
     */
    public function definition(): array
    {
        $period = JadwalPelajaran::activePeriod();

        return [
            'mata_pelajaran_id' => MataPelajaranFactory::new(),
            'semester' => $period['semester'],
            'tahun_ajaran' => $period['tahun_ajaran'],
            ...BobotNilai::kolomRapor(BobotNilai::BAWAAN),
        ];
    }
}
