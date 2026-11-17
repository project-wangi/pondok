<?php

use App\Filament\Pages\PengaturanBobotNilai;
use App\Models\BobotNilai;
use App\Models\MataPelajaran;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ERaporFactory;
use Database\Factories\GuruFactory;
use Database\Factories\KelasFactory;
use Database\Factories\MataPelajaranFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00:00', 'Asia/Jakarta'));
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

afterEach(function () {
    $this->travelBack();
});

function bobotKurikulumUser(string ...$permissions): User
{
    $role = Role::findOrCreate('kurikulum', 'web');

    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return User::factory()->create()->assignRole($role);
}

function bobotSubject(string $name = 'Matematika'): MataPelajaran
{
    return MataPelajaranFactory::new()->create(['nama_mapel' => $name]);
}

test('restricts the weight page to the view permission', function () {
    $teacher = GuruFactory::new()->create();
    $this->actingAs($teacher->user);

    Livewire::test(PengaturanBobotNilai::class)->assertForbidden();

    $role = Role::findOrCreate('kurikulum', 'web');
    $role->revokePermissionTo(Permission::findOrCreate('View:PengaturanBobotNilai', 'web'));
    $user = User::factory()->create()->assignRole($role);
    $this->actingAs($user);

    Livewire::test(PengaturanBobotNilai::class)->assertForbidden();

    $role->givePermissionTo('View:PengaturanBobotNilai');

    Livewire::test(PengaturanBobotNilai::class)->assertOk();

    $this->actingAs(adminPanelUser('View:PengaturanBobotNilai'));
    Livewire::test(PengaturanBobotNilai::class)->assertOk();
});

test('shows default weights and stores one global setting for the active period', function () {
    $this->actingAs(bobotKurikulumUser('View:PengaturanBobotNilai'));

    Livewire::test(PengaturanBobotNilai::class)
        ->assertSee('Bobot nilai semua mata pelajaran')
        ->assertSet('bobot.formatif', '10')
        ->assertSet('bobot.sumatif', '15')
        ->assertSet('bobot.asts', '20')
        ->assertSet('bobot.asas', '35')
        ->set('bobot.formatif', '0')
        ->set('bobot.sumatif', '0')
        ->set('bobot.asts', '0')
        ->set('bobot.asas', '100')
        ->set('bobot.kehadiran', '0')
        ->set('bobot.sikap', '0')
        ->set('bobot.keaktifan', '0')
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('Bobot nilai tersimpan');

    $this->assertDatabaseCount('bobot_nilais', 1);
    $this->assertDatabaseHas('bobot_nilais', [
        'mata_pelajaran_id' => null,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'bobot_formatif' => 0,
        'bobot_asas' => 100,
    ]);
});

test('rejects weights that do not total one hundred percent', function () {
    $this->actingAs(bobotKurikulumUser('View:PengaturanBobotNilai'));

    Livewire::test(PengaturanBobotNilai::class)
        ->set('bobot.asas', '30')
        ->call('simpan')
        ->assertHasErrors(['bobot'])
        ->assertSee('Total bobot harus 100%. Saat ini 95%.');

    expect(BobotNilai::query()->count())->toBe(0);
});

test('rejects invalid weight values', function (string $component, string $value, string $message) {
    $this->actingAs(bobotKurikulumUser('View:PengaturanBobotNilai'));

    Livewire::test(PengaturanBobotNilai::class)
        ->set('bobot.'.$component, $value)
        ->call('simpan')
        ->assertHasErrors(['bobot.'.$component])
        ->assertSee($message);

    expect(BobotNilai::query()->count())->toBe(0);
})->with([
    'text' => ['formatif', 'abc', 'Bobot Nilai Formatif harus berupa bilangan bulat.'],
    'above 100' => ['asas', '101', 'Bobot Sumatif Akhir Semester (ASAS) harus berada antara 0 dan 100.'],
    'blank' => ['sikap', '', 'Bobot Nilai Sikap wajib diisi.'],
]);

test('syncs the stored report snapshots for every subject when weights change', function () {
    $matematika = bobotSubject();
    $bahasaArab = bobotSubject('Bahasa Arab');
    $kelas = KelasFactory::new()->create();
    $raporMatematika = ERaporFactory::new()->create([
        'mata_pelajaran_id' => $matematika->id,
        'kelas_id' => $kelas->id,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
    ]);
    $raporBahasaArab = ERaporFactory::new()->create([
        'mata_pelajaran_id' => $bahasaArab->id,
        'kelas_id' => $kelas->id,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
    ]);
    $this->actingAs(bobotKurikulumUser('View:PengaturanBobotNilai'));

    Livewire::test(PengaturanBobotNilai::class)
        ->set('bobot.formatif', '5')
        ->set('bobot.asas', '40')
        ->call('simpan')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('e_rapors', [
        'id' => $raporMatematika->id,
        'bobot_formatif' => 5,
        'bobot_asas' => 40,
    ]);
    $this->assertDatabaseHas('e_rapors', [
        'id' => $raporBahasaArab->id,
        'bobot_formatif' => 5,
        'bobot_asas' => 40,
    ]);
});

test('keeps the saved weights when another period is opened', function () {
    $this->actingAs(bobotKurikulumUser('View:PengaturanBobotNilai'));

    Livewire::test(PengaturanBobotNilai::class)
        ->set('bobot.formatif', '20')
        ->set('bobot.asas', '25')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(BobotNilai::untuk('Ganjil', '2026/2027'))
        ->toMatchArray(['formatif' => 20, 'asas' => 25]);

    Livewire::test(PengaturanBobotNilai::class)
        ->set('semester', 'Genap')
        ->assertSet('bobot.formatif', '10')
        ->assertSet('bobot.asas', '35');
});
