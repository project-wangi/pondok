<?php

namespace Database\Factories;

use App\Models\AbsenPiket;
use App\Models\AbsenPiketMurid;
use App\Models\Murid;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AbsenPiketMurid> */
class AbsenPiketMuridFactory extends Factory
{
    protected $model = AbsenPiketMurid::class;

    /**
     * @return array{
     *     absen_piket_id: AbsenPiketFactory,
     *     murid_id: MuridFactory,
     *     nama_murid: \Closure,
     *     nis: \Closure,
     *     masuk: string,
     *     pulang: string
     * }
     */
    public function definition(): array
    {
        return [
            'absen_piket_id' => AbsenPiketFactory::new(),
            'murid_id' => MuridFactory::new(),
            'nama_murid' => fn (array $attributes): string => Murid::find($attributes['murid_id'])?->nama ?? fake()->name(),
            'nis' => fn (array $attributes): ?string => Murid::find($attributes['murid_id'])?->nis,
            'masuk' => fake()->randomElement(array_keys(AbsenPiket::STATUS_MASUK)),
            'pulang' => fake()->randomElement(array_keys(AbsenPiket::STATUS_PULANG)),
        ];
    }
}
