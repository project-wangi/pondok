<?php

use App\Filament\Resources\Murids\Pages\ListMurids;
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

function muridBulkAdmin(): User
{
    foreach (['ViewAny:Murid', 'Update:Murid'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    return User::factory()->create()->givePermissionTo(['ViewAny:Murid', 'Update:Murid']);
}

test('moves selected students to another class at once', function () {
    $admin = muridBulkAdmin();
    $asal = KelasFactory::new()->create(['nama_kelas' => 'VII']);
    $tujuan = KelasFactory::new()->create(['nama_kelas' => 'VIII']);
    $first = MuridFactory::new()->create(['kelas_id' => $asal->id, 'nama' => 'Ahmad']);
    $second = MuridFactory::new()->create(['kelas_id' => $asal->id, 'nama' => 'Bilal']);
    $other = MuridFactory::new()->create(['kelas_id' => $asal->id, 'nama' => 'Cholid']);
    $this->actingAs($admin);

    Livewire::test(ListMurids::class)
        ->callTableBulkAction('pindahKelas', [$first, $second], data: ['kelas_id' => $tujuan->id])
        ->assertHasNoTableBulkActionErrors();

    expect($first->fresh()->kelas_id)->toBe($tujuan->id)
        ->and($second->fresh()->kelas_id)->toBe($tujuan->id)
        ->and($other->fresh()->kelas_id)->toBe($asal->id);
});

test('rejects an invalid target class', function () {
    $admin = muridBulkAdmin();
    $asal = KelasFactory::new()->create(['nama_kelas' => 'VII']);
    $student = MuridFactory::new()->create(['kelas_id' => $asal->id]);
    $this->actingAs($admin);

    Livewire::test(ListMurids::class)
        ->callTableBulkAction('pindahKelas', [$student], data: ['kelas_id' => 999999])
        ->assertHasTableBulkActionErrors(['kelas_id']);

    expect($student->fresh()->kelas_id)->toBe($asal->id);
});

test('hides the bulk class move without update permission', function () {
    Permission::findOrCreate('ViewAny:Murid', 'web');
    $user = User::factory()->create()->givePermissionTo('ViewAny:Murid');
    $this->actingAs($user);

    Livewire::test(ListMurids::class)
        ->assertTableBulkActionHidden('pindahKelas');
});
