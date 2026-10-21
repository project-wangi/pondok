<?php

namespace Database\Factories;

use App\Models\AbsenRapat;
use App\Models\Guru;
use App\Models\JadwalRapat;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<AbsenRapat> */
class AbsenRapatFactory extends Factory
{
    protected $model = AbsenRapat::class;

    /**
     * @return array{
     *     jadwal_rapat_id: \Closure,
     *     guru_id: GuruFactory,
     *     user_id: \Closure,
     *     nama_guru: \Closure,
     *     agenda: string,
     *     tanggal: string,
     *     jam_mulai: string,
     *     jam_selesai: string,
     *     foto: string,
     *     dicatat_pada: Carbon
     * }
     */
    public function definition(): array
    {
        $day = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][now()->dayOfWeek];

        return [
            'jadwal_rapat_id' => fn (): int => JadwalRapat::query()->create([
                'tanggal' => now()->toDateString(),
                'hari' => $day,
                'jam_mulai' => '08:00:00',
                'jam_selesai' => '10:00:00',
                'agenda' => fake()->sentence(3),
            ])->id,
            'guru_id' => GuruFactory::new(),
            'user_id' => fn (array $attributes): ?int => Guru::find($attributes['guru_id'])?->user_id,
            'nama_guru' => fn (array $attributes): string => Guru::find($attributes['guru_id'])?->nama ?? fake()->name(),
            'agenda' => fake()->sentence(3),
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '10:00:00',
            'foto' => 'absen-rapat/'.now()->format('Y-m-d').'/selfie.jpg',
            'dicatat_pada' => now(),
        ];
    }
}
