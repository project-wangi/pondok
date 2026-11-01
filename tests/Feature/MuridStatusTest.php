<?php

use App\Filament\Resources\Murids\Pages\EditMurid;
use App\Filament\Resources\Murids\Pages\ListMurids;
use App\Models\Murid;
use App\Models\User;
use Carbon\CarbonImmutable;
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

function muridStatusAdmin(): User
{
    foreach (['ViewAny:Murid', 'View:Murid', 'Update:Murid'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    return User::factory()->create()->givePermissionTo(['ViewAny:Murid', 'View:Murid', 'Update:Murid']);
}

test('defaults new students to active', function () {
    $murid = MuridFactory::new()->create();

    expect($murid->fresh()->status)->toBe(Murid::STATUS_AKTIF);
});

test('shows only active students in the default list', function () {
    $admin = muridStatusAdmin();
    $aktif = MuridFactory::new()->create(['nama' => 'Siswa Aktif']);
    $lulus = MuridFactory::new()->create(['nama' => 'Siswa Lulus', 'status' => Murid::STATUS_LULUS]);
    $keluar = MuridFactory::new()->create(['nama' => 'Siswa Keluar', 'status' => Murid::STATUS_KELUAR]);
    $this->actingAs($admin);

    Livewire::test(ListMurids::class)
        ->assertCanSeeTableRecords([$aktif])
        ->assertCanNotSeeTableRecords([$lulus, $keluar]);
});

test('filters students by status', function () {
    $admin = muridStatusAdmin();
    $aktif = MuridFactory::new()->create(['nama' => 'Siswa Aktif']);
    $lulus = MuridFactory::new()->create(['nama' => 'Siswa Lulus', 'status' => Murid::STATUS_LULUS]);
    $this->actingAs($admin);

    Livewire::test(ListMurids::class)
        ->filterTable('status', Murid::STATUS_LULUS)
        ->assertCanSeeTableRecords([$lulus])
        ->assertCanNotSeeTableRecords([$aktif]);
});

test('changes the status of several students at once', function () {
    $admin = muridStatusAdmin();
    $kelas = KelasFactory::new()->create(['nama_kelas' => 'XII']);
    $first = MuridFactory::new()->create(['kelas_id' => $kelas->id, 'nama' => 'Ahmad']);
    $second = MuridFactory::new()->create(['kelas_id' => $kelas->id, 'nama' => 'Bilal']);
    $other = MuridFactory::new()->create(['kelas_id' => $kelas->id, 'nama' => 'Cholid']);
    $this->actingAs($admin);

    Livewire::test(ListMurids::class)
        ->callTableBulkAction('ubahStatus', [$first, $second], data: ['status' => Murid::STATUS_LULUS])
        ->assertHasNoTableBulkActionErrors();

    expect($first->fresh()->status)->toBe(Murid::STATUS_LULUS)
        ->and($second->fresh()->status)->toBe(Murid::STATUS_LULUS)
        ->and($other->fresh()->status)->toBe(Murid::STATUS_AKTIF);
});

test('validates the bulk status value', function () {
    $admin = muridStatusAdmin();
    $murid = MuridFactory::new()->create();
    $this->actingAs($admin);

    Livewire::test(ListMurids::class)
        ->callTableBulkAction('ubahStatus', [$murid], data: ['status' => 'Pindah'])
        ->assertHasTableBulkActionErrors(['status']);

    expect($murid->fresh()->status)->toBe(Murid::STATUS_AKTIF);
});

test('fills the status date automatically and clears the other date', function () {
    $murid = MuridFactory::new()->create();

    $this->travelTo(CarbonImmutable::parse('2026-06-20 09:00:00'));

    $murid->update(['status' => Murid::STATUS_LULUS]);
    $murid->refresh();

    expect($murid->tanggal_lulus?->toDateString())->toBe('2026-06-20')
        ->and($murid->tanggal_keluar)->toBeNull();

    $murid->update(['status' => Murid::STATUS_KELUAR]);
    $murid->refresh();

    expect($murid->tanggal_keluar?->toDateString())->toBe('2026-06-20')
        ->and($murid->tanggal_lulus)->toBeNull();

    $murid->update(['status' => Murid::STATUS_AKTIF]);
    $murid->refresh();

    expect($murid->tanggal_lulus)->toBeNull()
        ->and($murid->tanggal_keluar)->toBeNull();

    $this->travelBack();
});

test('keeps a manually adjusted status date', function () {
    $murid = MuridFactory::new()->create([
        'status' => Murid::STATUS_LULUS,
        'tanggal_lulus' => '2026-05-31',
    ]);

    $murid->update(['status' => Murid::STATUS_LULUS]);

    expect($murid->fresh()->tanggal_lulus?->toDateString())->toBe('2026-05-31');
});

test('sets the status date when changing status in bulk', function () {
    $admin = muridStatusAdmin();
    $first = MuridFactory::new()->create(['nama' => 'Ahmad']);
    $second = MuridFactory::new()->create(['nama' => 'Bilal']);
    $this->actingAs($admin);

    $this->travelTo(CarbonImmutable::parse('2026-06-20 09:00:00'));

    Livewire::test(ListMurids::class)
        ->callTableBulkAction('ubahStatus', [$first, $second], data: ['status' => Murid::STATUS_LULUS])
        ->assertHasNoTableBulkActionErrors();

    expect($first->fresh()->tanggal_lulus?->toDateString())->toBe('2026-06-20')
        ->and($second->fresh()->tanggal_lulus?->toDateString())->toBe('2026-06-20');

    $this->travelBack();
});

test('saves the student status from the edit form', function () {
    $admin = muridStatusAdmin();
    $murid = MuridFactory::new()->create([
        'nis' => '20260001',
        'nisn' => '1234567890',
        'nik' => '3273010101010001',
        'no_kk' => '3273010101010002',
    ]);
    $this->actingAs($admin);

    Livewire::test(EditMurid::class, ['record' => $murid->getRouteKey()])
        ->fillForm(['status' => Murid::STATUS_KELUAR])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($murid->fresh()->status)->toBe(Murid::STATUS_KELUAR);
});
