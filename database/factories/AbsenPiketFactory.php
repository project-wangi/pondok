<?php

namespace Database\Factories;

use App\Models\AbsenPiket;
use App\Models\Guru;
use App\Models\Kelas;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<AbsenPiket> */
class AbsenPiketFactory extends Factory
{
    protected $model = AbsenPiket::class;

    /**
     * @return array{
     *     kelas_id: KelasFactory,
     *     guru_id: GuruFactory,
     *     pencatat_id: \Closure,
     *     revisi: int,
     *     tanggal: string,
     *     nama_guru: \Closure,
     *     nama_kelas: \Closure,
     *     dicatat_pada: Carbon
     * }
     */
    public function definition(): array
    {
        return [
            'kelas_id' => KelasFactory::new(),
            'guru_id' => GuruFactory::new(),
            'pencatat_id' => fn (array $attributes): ?int => Guru::find($attributes['guru_id'])?->user_id,
            'revisi' => 1,
            'tanggal' => now()->toDateString(),
            'nama_guru' => fn (array $attributes): string => Guru::find($attributes['guru_id'])?->nama ?? fake()->name(),
            'nama_kelas' => fn (array $attributes): string => Kelas::find($attributes['kelas_id'])?->nama_kelas ?? fake()->word(),
            'dicatat_pada' => now(),
        ];
    }
}
