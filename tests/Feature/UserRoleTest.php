<?php

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

function userRoleAdmin(): User
{
    return adminPanelUser('ViewAny:User', 'View:User', 'Create:User', 'Update:User');
}

test('creates a user with more than one role', function () {
    $guru = Role::create(['name' => 'guru', 'guard_name' => 'web']);
    $panel = Role::create(['name' => 'panel_user', 'guard_name' => 'web']);
    $this->actingAs(userRoleAdmin());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Petugas Ganda',
            'email' => 'petugas@example.test',
            'password' => 'password',
            'roles' => [$guru->id, $panel->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'petugas@example.test')->sole();

    expect($user->roles->pluck('name')->sort()->values()->all())->toBe(['guru', 'panel_user']);
});

test('updates a user to multiple roles and shows every badge', function () {
    $guru = Role::create(['name' => 'guru', 'guard_name' => 'web']);
    $panel = Role::create(['name' => 'panel_user', 'guard_name' => 'web']);
    $user = User::factory()->create(['name' => 'Petugas Lama']);
    $user->assignRole($guru);
    $this->actingAs(userRoleAdmin());

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['roles' => [$guru->id, $panel->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->roles->pluck('name')->sort()->values()->all())->toBe(['guru', 'panel_user']);

    Livewire::test(ListUsers::class)
        ->assertSee('Petugas Lama')
        ->assertSee('Panel User');
});

test('keeps at least one role required', function () {
    Role::create(['name' => 'guru', 'guard_name' => 'web']);
    $this->actingAs(userRoleAdmin());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Tanpa Role',
            'email' => 'tanpa-role@example.test',
            'password' => 'password',
            'roles' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['roles']);

    expect(User::query()->where('email', 'tanpa-role@example.test')->exists())->toBeFalse();
});
