<?php

namespace Database\Factories;

use App\Models\Kelas;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Kelas> */
class KelasFactory extends Factory
{
    protected $model = Kelas::class;

    /** @return array{nama_kelas: string, unit: string} */
    public function definition(): array
    {
        $tingkat = fake()->numberBetween(7, 12);
        $romawi = [
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        return [
            'nama_kelas' => $romawi[$tingkat].' '.fake()->unique()->numerify('##'),
            'unit' => $tingkat <= 9 ? Kelas::UNIT_MTS : Kelas::UNIT_MA,
        ];
    }
}
