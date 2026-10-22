<?php

use App\Filament\Resources\Donasis\DonasiResource;
use App\Filament\Resources\Donasis\Pages\CreateDonasi;
use App\Filament\Resources\Donasis\Pages\EditDonasi;
use App\Filament\Resources\Donasis\Pages\ListDonasis;
use App\Models\Donasi;
use App\Models\Guru;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\DonasiFactory;
use Database\Factories\GuruFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-22 10:00:00', 'Asia/Jakarta'));
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

afterEach(function () {
    $this->travelBack();
});

function donasiUser(string ...$permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function donasiTeacher(): Guru
{
    return GuruFactory::new()->create();
}

test('shows the donation menu in the Keuangan group', function () {
    expect(DonasiResource::getNavigationGroup())->toBe('Keuangan')
        ->and(DonasiResource::getNavigationLabel())->toBe('Donasi')
        ->and(DonasiResource::getSlug())->toBe('donasi');
});

test('computes the nominal as fifty thousand per child', function () {
    expect(DonasiFactory::new()->create(['jumlah_anak' => 0])->nominal)->toBe(0)
        ->and(DonasiFactory::new()->create(['jumlah_anak' => 3])->nominal)->toBe(150000);
});

test('lets a teacher create their own donation once', function () {
    $guru = donasiTeacher();
    $this->actingAs($guru->user);

    expect(DonasiResource::canViewAny())->toBeTrue()
        ->and(DonasiResource::canCreate())->toBeTrue();

    Livewire::test(CreateDonasi::class)
        ->fillForm(['jumlah_anak' => 2])
        ->call('create')
        ->assertHasNoFormErrors();

    $donasi = Donasi::query()->sole();
    expect($donasi->guru_id)->toBe($guru->id)
        ->and($donasi->tanggal->toDateString())->toBe('2026-10-22')
        ->and($donasi->jumlah_anak)->toBe(2)
        ->and($donasi->nominal)->toBe(100000);

    expect(DonasiResource::canCreate())->toBeFalse();
    Livewire::test(CreateDonasi::class)->assertForbidden();
});

test('shows only the teachers own donation with the nominal column', function () {
    $own = DonasiFactory::new()->create(['jumlah_anak' => 2]);
    DonasiFactory::new()->create(['jumlah_anak' => 3]);
    $this->actingAs($own->guru->user);

    Livewire::test(ListDonasis::class)
        ->assertOk()
        ->assertSee('22/10/2026')
        ->assertSee('Rp 100.000')
        ->assertDontSee('Rp 150.000');
});

test('stores the donation date chosen in the form', function () {
    $guru = donasiTeacher();
    $this->actingAs($guru->user);

    Livewire::test(CreateDonasi::class)
        ->fillForm(['tanggal' => '2026-10-01', 'jumlah_anak' => 1])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Donasi::query()->sole()->tanggal->toDateString())->toBe('2026-10-01');
});

test('lets a teacher update their own number of children', function () {
    $donasi = DonasiFactory::new()->create(['jumlah_anak' => 1]);
    $this->actingAs($donasi->guru->user);

    Livewire::test(EditDonasi::class, ['record' => $donasi->getKey()])
        ->assertOk()
        ->fillForm(['jumlah_anak' => 4])
        ->call('save')
        ->assertHasNoFormErrors();

    $donasi->refresh();

    expect($donasi->jumlah_anak)->toBe(4)
        ->and($donasi->nominal)->toBe(200000);
});

test('blocks a teacher from editing another donation', function () {
    $own = DonasiFactory::new()->create();
    $other = DonasiFactory::new()->create();
    $this->actingAs($own->guru->user);

    Livewire::test(EditDonasi::class, ['record' => $other->getKey()])->assertNotFound();
});

test('does not allow teachers to delete donations', function () {
    $donasi = DonasiFactory::new()->create();
    $this->actingAs($donasi->guru->user);

    expect($donasi->guru->user->can('delete', $donasi))->toBeFalse()
        ->and($donasi->guru->user->can('deleteAny', Donasi::class))->toBeFalse();
});

test('lets permitted staff see every donation and create for any teacher', function () {
    $admin = donasiUser('ViewAny:Donasi', 'View:Donasi', 'Create:Donasi', 'Update:Donasi');
    $first = DonasiFactory::new()->create(['jumlah_anak' => 1]);
    $second = DonasiFactory::new()->create(['jumlah_anak' => 2]);
    $newTeacher = donasiTeacher();
    $this->actingAs($admin);

    Livewire::test(ListDonasis::class)
        ->assertOk()
        ->assertSee('Rp 50.000')
        ->assertSee('Rp 100.000');

    Livewire::test(CreateDonasi::class)
        ->fillForm(['guru_id' => $newTeacher->id, 'jumlah_anak' => 5])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('donasis', ['guru_id' => $newTeacher->id, 'jumlah_anak' => 5]);
});

test('rejects a second donation for the same teacher', function () {
    $this->actingAs(donasiUser('ViewAny:Donasi', 'Create:Donasi'));
    $guru = donasiTeacher();
    DonasiFactory::new()->create(['guru_id' => $guru->id]);

    Livewire::test(CreateDonasi::class)
        ->fillForm(['guru_id' => $guru->id, 'jumlah_anak' => 1])
        ->call('create')
        ->assertHasFormErrors(['guru_id']);

    $this->assertDatabaseCount('donasis', 1);
});
