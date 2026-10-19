<?php

namespace Database\Factories;

use App\Models\Murid;
use App\Models\PresensiMurid;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PresensiMurid> */
class PresensiMuridFactory extends Factory
{
    protected $model = PresensiMurid::class;

    /**
     * @return array{
     *     sesi_mengajar_id: SesiMengajarFactory,
     *     murid_id: MuridFactory,
     *     nama_murid: \Closure,
     *     nis: \Closure,
     *     status: string,
     *     catatan: null
     * }
     */
    public function definition(): array
    {
        return [
            'sesi_mengajar_id' => SesiMengajarFactory::new(),
            'murid_id' => MuridFactory::new(),
            'nama_murid' => fn (array $attributes): string => Murid::find($attributes['murid_id'])?->nama ?? fake()->name(),
            'nis' => fn (array $attributes): ?string => Murid::find($attributes['murid_id'])?->nis,
            'status' => 'Hadir',
            'catatan' => null,
        ];
    }
}
