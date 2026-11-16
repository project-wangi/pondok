<?php

use App\Filament\Resources\Murids\Pages\ListMurids;
use App\Models\Kelas;
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

function muridUnitAdmin(): User
{
    Permission::findOrCreate('ViewAny:Murid', 'web');

    return User::factory()->create()->givePermissionTo('ViewAny:Murid');
}

test('stores the unit given on the class instead of guessing from the name', function () {
    $kelas = Kelas::query()->create(['nama_kelas' => 'VII', 'unit' => Kelas::UNIT_MA]);

    expect($kelas->unit)->toBe(Kelas::UNIT_MA);
});

test('shows the unit column and filters students by unit', function () {
    $this->actingAs(muridUnitAdmin());

    $mts = KelasFactory::new()->create(['nama_kelas' => 'VIII', 'unit' => Kelas::UNIT_MTS]);
    $ma = KelasFactory::new()->create(['nama_kelas' => 'XI', 'unit' => Kelas::UNIT_MA]);
    $mtsStudent = MuridFactory::new()->create(['kelas_id' => $mts->id, 'nama' => 'Siswa MTs']);
    $maStudent = MuridFactory::new()->create(['kelas_id' => $ma->id, 'nama' => 'Siswa MA']);

    Livewire::test(ListMurids::class)
        ->assertTableColumnExists('kelas.unit')
        ->assertSee('Unit')
        ->filterTable('unit', Kelas::UNIT_MTS)
        ->assertCanSeeTableRecords([$mtsStudent])
        ->assertCanNotSeeTableRecords([$maStudent])
        ->filterTable('unit', Kelas::UNIT_MA)
        ->assertCanSeeTableRecords([$maStudent])
        ->assertCanNotSeeTableRecords([$mtsStudent]);
});

test('shows no students when the selected unit has no classes', function () {
    $this->actingAs(muridUnitAdmin());

    $mts = KelasFactory::new()->create(['nama_kelas' => 'VII', 'unit' => Kelas::UNIT_MTS]);
    $student = MuridFactory::new()->create(['kelas_id' => $mts->id]);

    Livewire::test(ListMurids::class)
        ->filterTable('unit', Kelas::UNIT_MA)
        ->assertCanNotSeeTableRecords([$student]);
});
