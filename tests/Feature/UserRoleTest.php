<?php

use App\Filament\Resources\Gurus\GuruResource;
use App\Filament\Resources\Jabatans\JabatanResource;
use App\Filament\Resources\JenisPeminatans\JenisPeminatanResource;
use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Resources\MataPelajarans\MataPelajaranResource;
use App\Filament\Resources\Murids\MuridResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
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

test('allows the curriculum and homeroom roles into the admin panel', function () {
    $roles = [
        'kurikulum' => 'View:PengaturanBobotNilai',
        'wali_kelas' => 'View:InputKepribadian',
    ];

    foreach ($roles as $roleName => $permission) {
        $role = Role::findOrCreate($roleName, 'web');
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $user = User::factory()->create()->assignRole($role);

        expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
    }
});

test('lets the curriculum role open every school management resource', function () {
    $role = Role::findOrCreate('kurikulum', 'web');
    $user = User::factory()->create()->assignRole($role);
    $this->actingAs($user);

    $resources = [
        GuruResource::class,
        JabatanResource::class,
        JenisPeminatanResource::class,
        KelasResource::class,
        MataPelajaranResource::class,
        MuridResource::class,
        UserResource::class,
    ];

    foreach ($resources as $resource) {
        $this->get($resource::getUrl('index'))->assertOk();
    }

    $this->get(KelasResource::getUrl('create'))->assertOk();
});

test('keeps school management user accounts read only for the curriculum role', function () {
    $role = Role::findOrCreate('kurikulum', 'web');
    $user = User::factory()->create()->assignRole($role);
    $this->actingAs($user);

    $this->get(UserResource::getUrl('create'))->assertForbidden();
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
