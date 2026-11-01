<?php

namespace Database\Factories;

use App\Models\CalonSiswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CalonSiswa>
 */
class CalonSiswaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();

        return [
            'user_id' => $user,
            'nomor_pendaftaran' => 'PPDB-'.now()->format('Y').'-'.strtoupper(Str::random(6)),
            'nama' => fake()->name(),
            'jenis_kelamin' => fake()->randomElement(array_keys(CalonSiswa::JENIS_KELAMIN)),
            'email' => fn (array $attributes): string => User::find($attributes['user_id'])?->email ?? fake()->unique()->safeEmail(),
            'nohp' => '08'.fake()->numerify('##########'),
            'nilai_tes' => null,
            'status' => CalonSiswa::STATUS_MENUNGGU,
            'catatan' => null,
            'murid_id' => null,
            'diterima_pada' => null,
        ];
    }
}
