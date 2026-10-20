<?php

namespace Database\Factories;

use App\Models\AsesmenFormatifNilai;
use App\Models\LingkupMateri;
use App\Models\Murid;
use App\Models\TujuanPembelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AsesmenFormatifNilai> */
class AsesmenFormatifNilaiFactory extends Factory
{
    protected $model = AsesmenFormatifNilai::class;

    /**
     * @return array{
     *     asesmen_formatif_id: AsesmenFormatifFactory,
     *     murid_id: MuridFactory,
     *     lingkup_materi_id: \Closure,
     *     tujuan_pembelajaran_id: TujuanPembelajaranFactory,
     *     nama_murid: \Closure,
     *     nis: \Closure,
     *     jenis_kelamin: \Closure,
     *     lingkup_materi: \Closure,
     *     tujuan_pembelajaran: \Closure,
     *     nilai: int
     * }
     */
    public function definition(): array
    {
        return [
            'asesmen_formatif_id' => AsesmenFormatifFactory::new(),
            'murid_id' => MuridFactory::new(),
            'tujuan_pembelajaran_id' => TujuanPembelajaranFactory::new(),
            'lingkup_materi_id' => fn (array $attributes): ?int => TujuanPembelajaran::find($attributes['tujuan_pembelajaran_id'])?->lingkup_materi_id,
            'nama_murid' => fn (array $attributes): string => Murid::find($attributes['murid_id'])?->nama ?? fake()->name(),
            'nis' => fn (array $attributes): ?string => Murid::find($attributes['murid_id'])?->nis,
            'jenis_kelamin' => fn (array $attributes): ?string => Murid::find($attributes['murid_id'])?->jenis_kelamin,
            'lingkup_materi' => fn (array $attributes): string => LingkupMateri::find($attributes['lingkup_materi_id'])?->materi ?? fake()->words(3, true),
            'tujuan_pembelajaran' => fn (array $attributes): string => TujuanPembelajaran::find($attributes['tujuan_pembelajaran_id'])?->teks ?? 'siswa '.fake()->words(4, true),
            'nilai' => fake()->numberBetween(60, 100),
        ];
    }
}
