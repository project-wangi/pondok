<?php

namespace Database\Factories;

use App\Models\Murid;
use App\Models\NilaiUjianNilai;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NilaiUjianNilai> */
class NilaiUjianNilaiFactory extends Factory
{
    protected $model = NilaiUjianNilai::class;

    /**
     * @return array{
     *     nilai_ujian_komponen_id: NilaiUjianKomponenFactory,
     *     murid_id: MuridFactory,
     *     nama_murid: \Closure,
     *     nis: \Closure,
     *     nilai: int,
     *     catatan: ?string
     * }
     */
    public function definition(): array
    {
        return [
            'nilai_ujian_komponen_id' => NilaiUjianKomponenFactory::new(),
            'murid_id' => MuridFactory::new(),
            'nama_murid' => fn (array $attributes): string => Murid::find($attributes['murid_id'])?->nama ?? fake()->name(),
            'nis' => fn (array $attributes): ?string => Murid::find($attributes['murid_id'])?->nis,
            'nilai' => fake()->numberBetween(60, 100),
            'catatan' => fake()->optional()->sentence(),
        ];
    }
}
