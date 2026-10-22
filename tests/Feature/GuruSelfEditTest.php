<?php

use App\Filament\Resources\Gurus\Pages\EditGuru;
use App\Filament\Resources\Gurus\Pages\ListGurus;
use App\Models\Guru;
use App\Models\Jabatan;
use App\Models\User;
use Database\Factories\GuruFactory;
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

function guruSelfUser(string ...$permissions): User
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

/** @param array<string, mixed> $attributes */
function guruSelfProfile(array $attributes = []): Guru
{
    return GuruFactory::new()->create($attributes);
}

test('shows only the teachers own profile in the guru list', function () {
    $own = guruSelfProfile(['user_id' => User::factory()->create(['name' => 'Guru Sendiri'])->id]);
    guruSelfProfile(['user_id' => User::factory()->create(['name' => 'Guru Lain'])->id]);
    $this->actingAs($own->user);

    Livewire::test(ListGurus::class)
        ->assertOk()
        ->assertSee('Guru Sendiri')
        ->assertDontSee('Guru Lain');
});

test('lets a teacher edit their own guru data', function () {
    $guru = guruSelfProfile();
    $this->actingAs($guru->user);

    Livewire::test(EditGuru::class, ['record' => $guru->getKey()])
        ->assertOk()
        ->fillForm([
            'agama' => 'Islam',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'laki-laki',
            'alamat' => 'Jl. Pesantren No. 1',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('gurus', [
        'id' => $guru->id,
        'agama' => 'Islam',
        'tempat_lahir' => 'Bandung',
        'alamat' => 'Jl. Pesantren No. 1',
    ]);
});

test('blocks a teacher from opening another guru record', function () {
    $own = guruSelfProfile();
    $other = guruSelfProfile();
    $this->actingAs($own->user);

    Livewire::test(EditGuru::class, ['record' => $other->getKey()])->assertNotFound();
});

test('ignores account and position changes submitted by a teacher', function () {
    $guru = guruSelfProfile();
    $otherUser = User::factory()->create();
    $jabatan = Jabatan::query()->create(['nama_jabatan' => 'Wali Kelas', 'gaji' => 0]);
    $this->actingAs($guru->user);

    Livewire::test(EditGuru::class, ['record' => $guru->getKey()])
        ->fillForm([
            'user_id' => $otherUser->id,
            'jabatans' => [$jabatan->id],
            'agama' => 'Islam',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'laki-laki',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($guru->fresh()->user_id)->toBe($guru->user_id)
        ->and($guru->fresh()->jabatans()->count())->toBe(0);
});

test('keeps create and delete unavailable for teachers', function () {
    $guru = guruSelfProfile();
    $this->actingAs($guru->user);

    expect($guru->user->can('create', Guru::class))->toBeFalse()
        ->and($guru->user->can('delete', $guru))->toBeFalse()
        ->and($guru->user->can('deleteAny', Guru::class))->toBeFalse();
});

test('still lets permitted staff see and edit every guru', function () {
    $admin = guruSelfUser('ViewAny:Guru', 'View:Guru', 'Update:Guru');
    $first = guruSelfProfile(['user_id' => User::factory()->create(['name' => 'Guru Pertama'])->id]);
    $second = guruSelfProfile(['user_id' => User::factory()->create(['name' => 'Guru Kedua'])->id]);
    $this->actingAs($admin);

    Livewire::test(ListGurus::class)
        ->assertOk()
        ->assertSee('Guru Pertama')
        ->assertSee('Guru Kedua');

    Livewire::test(EditGuru::class, ['record' => $second->getKey()])
        ->assertOk()
        ->fillForm([
            'user_id' => $second->user_id,
            'agama' => $second->agama ?? 'Islam',
            'tempat_lahir' => 'Surabaya',
            'tanggal_lahir' => $second->tanggal_lahir ?? '1990-01-01',
            'jenis_kelamin' => 'laki-laki',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('gurus', ['id' => $second->id, 'tempat_lahir' => 'Surabaya']);
});
