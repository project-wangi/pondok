<?php

namespace Database\Factories;

use App\Models\NilaiUjianKomponen;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NilaiUjianKomponen> */
class NilaiUjianKomponenFactory extends Factory
{
    protected $model = NilaiUjianKomponen::class;

    /**
     * @return array{
     *     nilai_ujian_id: NilaiUjianFactory,
     *     urutan: \Closure,
     *     nama: \Closure
     * }
     */
    public function definition(): array
    {
        return [
            'nilai_ujian_id' => NilaiUjianFactory::new(),
            'urutan' => fn (array $attributes): int => (int) NilaiUjianKomponen::query()
                ->where('nilai_ujian_id', $attributes['nilai_ujian_id'])
                ->max('urutan') + 1,
            'nama' => fn (array $attributes): string => 'Ujian '.$attributes['urutan'],
        ];
    }
}
