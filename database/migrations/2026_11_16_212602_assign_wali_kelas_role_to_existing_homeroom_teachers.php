<?php

use App\Models\Guru;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Guru yang sudah tercatat sebagai wali kelas sebelum fitur ini ada belum
     * memiliki role wali_kelas, jadi diberikan sekali di sini.
     */
    public function up(): void
    {
        $role = Role::findOrCreate('wali_kelas', 'web');

        Guru::query()
            ->whereNotNull('kelas_id')
            ->whereNotNull('user_id')
            ->with('user')
            ->each(function (Guru $guru) use ($role): void {
                $user = $guru->user;

                if ($user !== null && ! $user->hasRole($role)) {
                    $user->assignRole($role);
                }
            });
    }

    public function down(): void
    {
        // Role tetap dipertahankan saat rollback.
    }
};
