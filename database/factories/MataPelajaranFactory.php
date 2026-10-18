<?php

namespace Database\Factories;

use App\Models\MataPelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MataPelajaran> */
class MataPelajaranFactory extends Factory
{
    protected $model = MataPelajaran::class;

    public function definition(): array
    {
        return ['kode_mapel' => fake()->unique()->bothify('MP###'), 'nama_mapel' => fake()->words(2, true)];
    }
}
