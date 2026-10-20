<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\LingkupMateri;
use App\Models\MataPelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LingkupMateri> */
class LingkupMateriFactory extends Factory
{
    protected $model = LingkupMateri::class;

    public function definition(): array
    {
        $period = JadwalPelajaran::activePeriod();

        return [
            'guru_id' => GuruFactory::new(),
            'mata_pelajaran_id' => MataPelajaranFactory::new(),
            'semester' => $period['semester'],
            'tahun_ajaran' => $period['tahun_ajaran'],
            'urutan' => 1,
            'materi' => fake()->sentence(3),
        ];
    }

    public function forTeacher(Guru $guru): static
    {
        return $this->state(['guru_id' => $guru->id]);
    }

    public function forSubject(MataPelajaran $subject): static
    {
        return $this->state(['mata_pelajaran_id' => $subject->id]);
    }
}
