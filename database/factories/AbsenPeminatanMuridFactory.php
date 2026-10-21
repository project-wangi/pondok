<?php

namespace Database\Factories;

use App\Models\AbsenPeminatan;
use App\Models\AbsenPeminatanMurid;
use App\Models\Murid;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AbsenPeminatanMurid> */
class AbsenPeminatanMuridFactory extends Factory
{
    protected $model = AbsenPeminatanMurid::class;

    /**
     * @return array{
     *     absen_peminatan_id: AbsenPeminatanFactory,
     *     murid_id: MuridFactory,
     *     nama_murid: \Closure,
     *     nis: \Closure,
     *     nama_kelas: \Closure,
     *     status: string
     * }
     */
    public function definition(): array
    {
        return [
            'absen_peminatan_id' => AbsenPeminatanFactory::new(),
            'murid_id' => MuridFactory::new(),
            'nama_murid' => fn (array $attributes): string => Murid::find($attributes['murid_id'])?->nama ?? fake()->name(),
            'nis' => fn (array $attributes): ?string => Murid::find($attributes['murid_id'])?->nis,
            'nama_kelas' => fn (array $attributes): ?string => Murid::find($attributes['murid_id'])?->kelas?->nama_kelas,
            'status' => fake()->randomElement(array_keys(AbsenPeminatan::STATUSES)),
        ];
    }
}
