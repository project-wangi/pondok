<?php

namespace Database\Factories;

use App\Models\AbsenPeminatan;
use App\Models\Guru;
use App\Models\JenisPeminatan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<AbsenPeminatan> */
class AbsenPeminatanFactory extends Factory
{
    protected $model = AbsenPeminatan::class;

    /**
     * @return array{
     *     jenis_peminatan_id: \Closure,
     *     guru_id: GuruFactory,
     *     pencatat_id: \Closure,
     *     revisi: int,
     *     tanggal: string,
     *     nama_guru: \Closure,
     *     nama_peminatan: \Closure,
     *     dicatat_pada: Carbon
     * }
     */
    public function definition(): array
    {
        return [
            'jenis_peminatan_id' => fn (): int => JenisPeminatan::query()->create([
                'nama_peminatan' => fake()->unique()->words(2, true),
                'nominal' => 100000,
            ])->id,
            'guru_id' => GuruFactory::new(),
            'pencatat_id' => fn (array $attributes): ?int => Guru::find($attributes['guru_id'])?->user_id,
            'revisi' => 1,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '09:00:00',
            'nama_guru' => fn (array $attributes): string => Guru::find($attributes['guru_id'])?->nama ?? fake()->name(),
            'nama_peminatan' => fn (array $attributes): string => JenisPeminatan::find($attributes['jenis_peminatan_id'])?->nama_peminatan ?? fake()->words(2, true),
            'dicatat_pada' => now(),
        ];
    }
}
