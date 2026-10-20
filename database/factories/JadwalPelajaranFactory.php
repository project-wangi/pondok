<?php

namespace Database\Factories;

use App\Models\JadwalPelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JadwalPelajaran> */
class JadwalPelajaranFactory extends Factory
{
    protected $model = JadwalPelajaran::class;

    public function definition(): array
    {
        return [
            'kelas_id' => KelasFactory::new(),
            'guru_id' => GuruFactory::new(),
            'mata_pelajaran' => MataPelajaranFactory::new(),
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '09:00:00',
            ...JadwalPelajaran::activePeriod(),
        ];
    }
}
