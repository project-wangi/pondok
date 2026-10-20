<?php

namespace Database\Factories;

use App\Models\TujuanPembelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TujuanPembelajaran> */
class TujuanPembelajaranFactory extends Factory
{
    protected $model = TujuanPembelajaran::class;

    public function definition(): array
    {
        return [
            'lingkup_materi_id' => LingkupMateriFactory::new(),
            'urutan' => 1,
            'teks' => fake()->sentence(6),
        ];
    }
}
