<?php

namespace Database\Factories;

use App\Models\Kelas;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Kelas> */
class KelasFactory extends Factory
{
    protected $model = Kelas::class;

    public function definition(): array
    {
        return ['nama_kelas' => fake()->unique()->bothify('Kelas ##?')];
    }
}
