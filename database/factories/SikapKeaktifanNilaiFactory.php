<?php

namespace Database\Factories;

use App\Models\Murid;
use App\Models\SikapKeaktifan;
use App\Models\SikapKeaktifanNilai;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SikapKeaktifanNilai> */
class SikapKeaktifanNilaiFactory extends Factory
{
    protected $model = SikapKeaktifanNilai::class;

    /**
     * @return array{
     *     sikap_keaktifan_id: SikapKeaktifanFactory,
     *     murid_id: MuridFactory,
     *     nama_murid: \Closure,
     *     nis: \Closure,
     *     jenis_kelamin: \Closure,
     *     sikap: string,
     *     keaktifan: string
     * }
     */
    public function definition(): array
    {
        $kategori = array_keys(SikapKeaktifan::KATEGORI);

        return [
            'sikap_keaktifan_id' => SikapKeaktifanFactory::new(),
            'murid_id' => MuridFactory::new(),
            'nama_murid' => fn (array $attributes): string => Murid::find($attributes['murid_id'])?->nama ?? fake()->name(),
            'nis' => fn (array $attributes): ?string => Murid::find($attributes['murid_id'])?->nis,
            'jenis_kelamin' => fn (array $attributes): ?string => Murid::find($attributes['murid_id'])?->jenis_kelamin,
            'sikap' => fake()->randomElement($kategori),
            'keaktifan' => fake()->randomElement($kategori),
        ];
    }
}
