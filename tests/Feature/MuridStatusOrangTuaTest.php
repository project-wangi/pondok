<?php

use App\Filament\Resources\Murids\Pages\CreateMurid;
use App\Filament\Resources\Murids\Pages\EditMurid;
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

function statusMuridUser(string ...$permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    return User::factory()->create()->givePermissionTo($permissions);
}

/** @return array<string, mixed> */
function statusMuridData(string $email = 'siswa.status@example.test'): array
{
    return [
        'kelas_id' => KelasFactory::new()->create()->id,
        'nama' => 'Siswa Status',
        'nisn' => '1234567890',
        'nis' => '2026002',
        'nik' => '3201234567890003',
        'no_kk' => '3201234567890004',
        'email' => $email,
        'jenis_kelamin' => 'laki-laki',
    ];
}

test('stores the parent status chosen in the form', function () {
    $this->actingAs(statusMuridUser('ViewAny:Murid', 'Create:Murid'));

    Livewire::test(CreateMurid::class)
        ->fillForm([
            ...statusMuridData(),
            'status_ayah' => 'Meninggal',
            'status_ibu' => 'Hidup',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('murids', [
        'email' => 'siswa.status@example.test',
        'status_ayah' => 'Meninggal',
        'status_ibu' => 'Hidup',
    ]);
});

test('defaults both parent statuses to Hidup', function () {
    $this->actingAs(statusMuridUser('ViewAny:Murid', 'Create:Murid'));

    Livewire::test(CreateMurid::class)
        ->fillForm(statusMuridData())
        ->call('create')
        ->assertHasNoFormErrors();

    $murid = Murid::query()->where('email', 'siswa.status@example.test')->sole();

    expect($murid->status_ayah)->toBe('Hidup')
        ->and($murid->status_ibu)->toBe('Hidup');
});

test('updates the parent status when editing a student', function () {
    $this->actingAs(statusMuridUser('ViewAny:Murid', 'Update:Murid'));
    $murid = MuridFactory::new()->create(['kelas_id' => KelasFactory::new()->create()->id]);

    Livewire::test(EditMurid::class, ['record' => $murid->getKey()])
        ->fillForm([
            ...statusMuridData($murid->email),
            'status_ayah' => 'Hidup',
            'status_ibu' => 'Meninggal',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('murids', [
        'id' => $murid->id,
        'status_ayah' => 'Hidup',
        'status_ibu' => 'Meninggal',
    ]);
});
