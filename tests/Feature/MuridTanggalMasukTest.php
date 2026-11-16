<?php

use App\Filament\Resources\Murids\Pages\CreateMurid;
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
    $this->travelTo(CarbonImmutable::parse('2026-07-15 09:00:00', 'Asia/Jakarta'));
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

afterEach(function () {
    $this->travelBack();
});

function tanggalMasukUser(string ...$permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    return User::factory()->create()->givePermissionTo($permissions);
}

/** @return array<string, mixed> */
function tanggalMasukData(): array
{
    return [
        'kelas_id' => KelasFactory::new()->create()->id,
        'nama' => 'Siswa Baru',
        'nisn' => '1234500001',
        'nis' => '2026003',
        'nik' => '3201234567890005',
        'no_kk' => '3201234567890006',
        'email' => 'siswa.masuk@example.test',
        'jenis_kelamin' => 'laki-laki',
    ];
}

test('defaults the entry date to today when creating a student', function () {
    $this->actingAs(tanggalMasukUser('ViewAny:Murid', 'Create:Murid'));

    Livewire::test(CreateMurid::class)
        ->fillForm(tanggalMasukData())
        ->call('create')
        ->assertHasNoFormErrors();

    $murid = Murid::query()->where('email', 'siswa.masuk@example.test')->sole();

    expect($murid->tanggal_masuk?->toDateString())->toBe('2026-07-15');
});

test('stores a custom entry date', function () {
    $this->actingAs(tanggalMasukUser('ViewAny:Murid', 'Create:Murid'));

    Livewire::test(CreateMurid::class)
        ->fillForm([
            ...tanggalMasukData(),
            'tanggal_masuk' => '2026-07-01',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Murid::query()->where('email', 'siswa.masuk@example.test')->sole()->tanggal_masuk?->toDateString())
        ->toBe('2026-07-01');
});

test('shows the entry date column in the student table', function () {
    $murid = MuridFactory::new()->create(['tanggal_masuk' => '2026-07-15']);
    $this->actingAs(tanggalMasukUser('ViewAny:Murid'));

    Livewire::test(ListMurids::class)
        ->assertTableColumnExists('tanggal_masuk')
        ->assertCanSeeTableRecords([$murid]);
});
