<?php

namespace Database\Factories;

use App\Models\Donasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Donasi> */
class DonasiFactory extends Factory
{
    protected $model = Donasi::class;

    /**
     * @return array{guru_id: GuruFactory, tanggal: string, jumlah_anak: int}
     */
    public function definition(): array
    {
        return [
            'guru_id' => GuruFactory::new(),
            'tanggal' => now()->toDateString(),
            'jumlah_anak' => fake()->numberBetween(0, 5),
        ];
    }
}
