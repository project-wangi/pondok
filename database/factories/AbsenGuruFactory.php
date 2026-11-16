<?php

namespace Database\Factories;

use App\Models\AbsenGuru;
use App\Models\Guru;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<AbsenGuru> */
class AbsenGuruFactory extends Factory
{
    protected $model = AbsenGuru::class;

    /**
     * @return array{
     *     guru_id: GuruFactory,
     *     user_id: \Closure,
     *     nama_guru: \Closure,
     *     tanggal: string,
     *     masuk_pada: Carbon,
     *     pulang_pada: Carbon
     * }
     */
    public function definition(): array
    {
        return [
            'guru_id' => GuruFactory::new(),
            'user_id' => fn (array $attributes): ?int => Guru::find($attributes['guru_id'])?->user_id,
            'nama_guru' => fn (array $attributes): string => Guru::find($attributes['guru_id'])?->nama ?? fake()->name(),
            'tanggal' => now()->toDateString(),
            'masuk_pada' => now()->setTime(7, 0),
            'pulang_pada' => now()->setTime(14, 0),
        ];
    }
}
