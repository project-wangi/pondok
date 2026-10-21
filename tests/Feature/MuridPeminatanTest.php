<?php

use App\Filament\Resources\Murids\Pages\CreateMurid;
use App\Filament\Resources\Murids\Pages\EditMurid;
use App\Models\JenisPeminatan;
use App\Models\Murid;
use App\Models\User;
use Database\Factories\KelasFactory;
use Database\Factories\MuridFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

function muridPeminatanUser(string ...$permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    return User::factory()->create()->givePermissionTo($permissions);
}

function muridPeminatan(string $name): JenisPeminatan
{
    return JenisPeminatan::query()->create([
        'nama_peminatan' => $name,
        'nominal' => 100000,
    ]);
}

/** @return array<string, mixed> */
function muridPeminatanFormData(): array
{
    return [
        'kelas_id' => KelasFactory::new()->create()->id,
        'nama' => 'Siswa Baru',
        'nisn' => '1234567890',
        'nis' => '2026001',
        'nik' => '3201234567890001',
        'no_kk' => '3201234567890002',
        'email' => 'siswabaru@example.test',
        'jenis_kelamin' => 'laki-laki',
    ];
}

test('stores multiple jenis peminatan when creating a student', function () {
    $this->actingAs(muridPeminatanUser('ViewAny:Murid', 'Create:Murid'));
    $tahfidz = muridPeminatan('Tahfidz');
    $bahasaArab = muridPeminatan('Bahasa Arab');

    Livewire::test(CreateMurid::class)
        ->fillForm([
            ...muridPeminatanFormData(),
            'jenisPeminatans' => [$tahfidz->id, $bahasaArab->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $murid = Murid::query()->sole();

    expect($murid->jenisPeminatans->pluck('id')->sort()->values()->all())
        ->toBe([$tahfidz->id, $bahasaArab->id]);

    $this->assertDatabaseCount('jenis_peminatan_murid', 2);
});

test('allows creating a student without jenis peminatan', function () {
    $this->actingAs(muridPeminatanUser('ViewAny:Murid', 'Create:Murid'));

    Livewire::test(CreateMurid::class)
        ->fillForm(muridPeminatanFormData())
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseCount('murids', 1);
    $this->assertDatabaseCount('jenis_peminatan_murid', 0);
});

test('syncs jenis peminatan when editing a student', function () {
    $this->actingAs(muridPeminatanUser('ViewAny:Murid', 'Update:Murid'));
    $tahfidz = muridPeminatan('Tahfidz');
    $bahasaArab = muridPeminatan('Bahasa Arab');
    $tahsin = muridPeminatan('Tahsin');
    $murid = MuridFactory::new()->create(['kelas_id' => KelasFactory::new()->create()->id]);
    $murid->jenisPeminatans()->attach([$tahfidz->id, $bahasaArab->id]);

    Livewire::test(EditMurid::class, ['record' => $murid->getKey()])
        ->fillForm([
            ...muridPeminatanFormData(),
            'jenisPeminatans' => [$bahasaArab->id, $tahsin->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($murid->fresh()->jenisPeminatans->pluck('id')->sort()->values()->all())
        ->toBe([$bahasaArab->id, $tahsin->id]);

    $this->assertDatabaseCount('jenis_peminatan_murid', 2);
});
