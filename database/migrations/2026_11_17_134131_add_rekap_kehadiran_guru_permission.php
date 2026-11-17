<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Rekap kehadiran guru hanya untuk pengawasan admin dan super admin.
     *
     * @var array<string, list<string>>
     */
    private const PERMISSIONS = [
        'View:RekapKehadiranGuru' => ['admin', 'super_admin'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        foreach (self::PERMISSIONS as $name => $roles) {
            $permission = Permission::findOrCreate($name, 'web');

            foreach ($roles as $roleName) {
                $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
                $role?->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        // Izin tetap dipertahankan saat rollback.
    }
};
