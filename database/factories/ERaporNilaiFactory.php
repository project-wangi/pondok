<?php

namespace Database\Factories;

use App\Models\ERaporNilai;
use App\Models\Murid;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ERaporNilai> */
class ERaporNilaiFactory extends Factory
{
    protected $model = ERaporNilai::class;

    /**
     * @return array{
     *     e_rapor_id: ERaporFactory,
     *     murid_id: MuridFactory,
     *     nama_murid: \Closure,
     *     nis: \Closure,
     *     asts: int,
     *     asas: int
     * }
     */
    public function definition(): array
    {
        return [
            'e_rapor_id' => ERaporFactory::new(),
            'murid_id' => MuridFactory::new(),
            'nama_murid' => fn (array $attributes): string => Murid::find($attributes['murid_id'])?->nama ?? fake()->name(),
            'nis' => fn (array $attributes): ?string => Murid::find($attributes['murid_id'])?->nis,
            'asts' => fake()->numberBetween(60, 100),
            'asas' => fake()->numberBetween(60, 100),
        ];
    }
}
