<?php

namespace Database\Factories;

use App\Models\Murid;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Murid> */
class MuridFactory extends Factory
{
    protected $model = Murid::class;

    /** @return array{kelas_id: KelasFactory, nama: string, email: string, jenis_kelamin: string} */
    public function definition(): array
    {
        return [
            'kelas_id' => KelasFactory::new(),
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'jenis_kelamin' => fake()->randomElement(['laki-laki', 'perempuan']),
        ];
    }
}
