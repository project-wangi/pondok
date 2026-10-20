<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\SesiMengajar;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<SesiMengajar> */
class SesiMengajarFactory extends Factory
{
    protected $model = SesiMengajar::class;

    /**
     * @return array{
     *     jadwal_pelajaran_id: JadwalPelajaranFactory,
     *     guru_id: \Closure,
     *     kelas_id: \Closure,
     *     pencatat_id: \Closure,
     *     revisi: int,
     *     tanggal: string,
     *     semester: \Closure,
     *     tahun_ajaran: \Closure,
     *     nama_guru: \Closure,
     *     nama_kelas: \Closure,
     *     nama_mapel: \Closure,
     *     jam_mulai: \Closure,
     *     jam_selesai: \Closure,
     *     dicatat_pada: Carbon
     * }
     */
    public function definition(): array
    {
        return [
            'jadwal_pelajaran_id' => JadwalPelajaranFactory::new(),
            'guru_id' => fn (array $attributes): ?int => JadwalPelajaran::find($attributes['jadwal_pelajaran_id'])?->guru_id,
            'kelas_id' => fn (array $attributes): ?int => JadwalPelajaran::find($attributes['jadwal_pelajaran_id'])?->kelas_id,
            'pencatat_id' => fn (array $attributes): ?int => Guru::find($attributes['guru_id'])?->user_id,
            'revisi' => 1,
            'tanggal' => now()->toDateString(),
            'semester' => fn (array $attributes): string => JadwalPelajaran::find($attributes['jadwal_pelajaran_id'])?->semester ?? JadwalPelajaran::activePeriod()['semester'],
            'tahun_ajaran' => fn (array $attributes): string => JadwalPelajaran::find($attributes['jadwal_pelajaran_id'])?->tahun_ajaran ?? JadwalPelajaran::activePeriod()['tahun_ajaran'],
            'nama_guru' => fn (array $attributes): string => Guru::find($attributes['guru_id'])?->nama ?? fake()->name(),
            'nama_kelas' => fn (array $attributes): string => Kelas::find($attributes['kelas_id'])?->nama_kelas ?? fake()->word(),
            'nama_mapel' => fn (array $attributes): string => JadwalPelajaran::find($attributes['jadwal_pelajaran_id'])?->mataPelajaran?->nama_mapel ?? fake()->words(2, true),
            'jam_mulai' => fn (array $attributes): ?string => JadwalPelajaran::find($attributes['jadwal_pelajaran_id'])?->jam_mulai,
            'jam_selesai' => fn (array $attributes): ?string => JadwalPelajaran::find($attributes['jadwal_pelajaran_id'])?->jam_selesai,
            'dicatat_pada' => now(),
        ];
    }
}
