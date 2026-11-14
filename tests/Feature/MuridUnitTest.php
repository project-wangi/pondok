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

test('derives the unit from the class name', function (string $nama, ?string $unit) {
    expect(Kelas::query()->create(['nama_kelas' => $nama])->unit)->toBe($unit);
})->with([
    ['VII', Kelas::UNIT_MTS],
    ['VIII A', Kelas::UNIT_MTS],
    ['IX', Kelas::UNIT_MTS],
    ['7', Kelas::UNIT_MTS],
    ['Kelas VII', Kelas::UNIT_MTS],
    ['X', Kelas::UNIT_MA],
    ['XI', Kelas::UNIT_MA],
    ['XII A', Kelas::UNIT_MA],
    ['12', Kelas::UNIT_MA],
    ['Lain', null],
]);

test('shows the unit column and filters students by unit', function () {
    $this->actingAs(muridUnitAdmin());

    $mts = KelasFactory::new()->create(['nama_kelas' => 'VIII']);
    $ma = KelasFactory::new()->create(['nama_kelas' => 'XI']);
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

    $mts = KelasFactory::new()->create(['nama_kelas' => 'VII']);
    $student = MuridFactory::new()->create(['kelas_id' => $mts->id]);

    Livewire::test(ListMurids::class)
        ->filterTable('unit', Kelas::UNIT_MA)
        ->assertCanNotSeeTableRecords([$student]);
});
