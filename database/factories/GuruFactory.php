<?php

namespace Database\Factories;

use App\Models\Guru;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** @extends Factory<Guru> */
class GuruFactory extends Factory
{
    /**
     * Izin halaman guru yang dipakai tes. Di produksi daftar ini diatur
     * lewat menu Role & Permission (role guru).
     *
     * @var list<string>
     */
    public const PAGE_PERMISSIONS = [
        'View:AbsensiPeminatan',
        'View:AbsensiPiket',
        'View:AbsensiRapat',
        'View:InputAsesmenFormatif',
        'View:InputERapor',
        'View:InputLingkupMateri',
        'View:InputNilaiUjian',
        'View:InputSikapKeaktifan',
        'View:JurnalMengajar',
        'View:PresensiSiswa',
        'View:RekapAbsensi',
        'View:RekapAbsensiPeminatan',
        'View:RekapAbsensiPiket',
        'View:SlipGaji',
        'ViewAny:AbsenRapat',
    ];

    protected $model = Guru::class;

    public function definition(): array
    {
        return ['user_id' => UserFactory::new(), 'jenis_kelamin' => 'Laki-laki'];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (): void {
            $role = Role::findOrCreate('guru', 'web');

            $role->givePermissionTo(array_map(
                fn (string $name): Permission => Permission::findOrCreate($name, 'web'),
                self::PAGE_PERMISSIONS,
            ));
        });
    }
}
