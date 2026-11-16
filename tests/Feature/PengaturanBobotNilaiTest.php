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

test('shows default weights and stores them for the active period', function () {
    $subject = bobotSubject();
    $this->actingAs(bobotKurikulumUser('View:PengaturanBobotNilai'));

    Livewire::test(PengaturanBobotNilai::class)
        ->assertSee('Matematika')
        ->assertSet('bobot.'.$subject->id.'.formatif', '10')
        ->assertSet('bobot.'.$subject->id.'.sumatif', '15')
        ->assertSet('bobot.'.$subject->id.'.asts', '20')
        ->assertSet('bobot.'.$subject->id.'.asas', '35')
        ->set('bobot.'.$subject->id.'.formatif', '0')
        ->set('bobot.'.$subject->id.'.sumatif', '0')
        ->set('bobot.'.$subject->id.'.asts', '0')
        ->set('bobot.'.$subject->id.'.asas', '100')
        ->set('bobot.'.$subject->id.'.kehadiran', '0')
        ->set('bobot.'.$subject->id.'.sikap', '0')
        ->set('bobot.'.$subject->id.'.keaktifan', '0')
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertNotified('Bobot nilai tersimpan');

    $this->assertDatabaseHas('bobot_nilais', [
        'mata_pelajaran_id' => $subject->id,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
        'bobot_formatif' => 0,
        'bobot_asas' => 100,
    ]);
});

test('rejects weights that do not total one hundred percent', function () {
    $subject = bobotSubject();
    $this->actingAs(bobotKurikulumUser('View:PengaturanBobotNilai'));

    Livewire::test(PengaturanBobotNilai::class)
        ->set('bobot.'.$subject->id.'.asas', '30')
        ->call('simpan')
        ->assertHasErrors(['bobot.'.$subject->id])
        ->assertSee('Total bobot Matematika harus 100%. Saat ini 95%.');

    expect(BobotNilai::query()->count())->toBe(0);
});

test('rejects invalid weight values', function (string $component, string $value, string $message) {
    $subject = bobotSubject();
    $this->actingAs(bobotKurikulumUser('View:PengaturanBobotNilai'));

    Livewire::test(PengaturanBobotNilai::class)
        ->set('bobot.'.$subject->id.'.'.$component, $value)
        ->call('simpan')
        ->assertHasErrors(['bobot.'.$subject->id.'.'.$component])
        ->assertSee($message);

    expect(BobotNilai::query()->count())->toBe(0);
})->with([
    'text' => ['formatif', 'abc', 'Bobot Nilai Formatif Matematika harus berupa bilangan bulat.'],
    'above 100' => ['asas', '101', 'Bobot Sumatif Akhir Semester (ASAS) Matematika harus berada antara 0 dan 100.'],
    'blank' => ['sikap', '', 'Bobot Nilai Sikap Matematika wajib diisi.'],
]);

test('syncs the stored report snapshots when weights change', function () {
    $subject = bobotSubject();
    $kelas = KelasFactory::new()->create();
    $rapor = ERaporFactory::new()->create([
        'mata_pelajaran_id' => $subject->id,
        'kelas_id' => $kelas->id,
        'semester' => 'Ganjil',
        'tahun_ajaran' => '2026/2027',
    ]);
    $this->actingAs(bobotKurikulumUser('View:PengaturanBobotNilai'));

    Livewire::test(PengaturanBobotNilai::class)
        ->set('bobot.'.$subject->id.'.formatif', '5')
        ->set('bobot.'.$subject->id.'.asas', '40')
        ->call('simpan')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('e_rapors', [
        'id' => $rapor->id,
        'bobot_formatif' => 5,
        'bobot_asas' => 40,
    ]);
});
