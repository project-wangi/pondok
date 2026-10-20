<?php

namespace Database\Factories;

use App\Models\JurnalMengajarBaris;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JurnalMengajarBaris> */
class JurnalMengajarBarisFactory extends Factory
{
    protected $model = JurnalMengajarBaris::class;

    public function definition(): array
    {
        return [
            'jurnal_mengajar_id' => JurnalMengajarFactory::new(),
            'lingkup_materi_id' => null,
            'tujuan_pembelajaran_id' => null,
            'lingkup_materi' => fake()->words(3, true),
            'tujuan_pembelajaran' => 'siswa '.fake()->words(4, true),
            'kegiatan' => fake()->sentence(8),
            'catatan' => null,
            'tindak_lanjut' => null,
            'urutan' => 1,
        ];
    }
}
