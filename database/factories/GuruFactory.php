<?php

namespace Database\Factories;

use App\Models\Guru;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Guru> */
class GuruFactory extends Factory
{
    protected $model = Guru::class;

    public function definition(): array
    {
        return ['user_id' => UserFactory::new(), 'jenis_kelamin' => 'Laki-laki'];
    }
}
