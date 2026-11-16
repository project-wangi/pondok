<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const ROLES = ['kurikulum', 'wali_kelas'];

    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $now = now();

        foreach (self::ROLES as $name) {
            DB::table('roles')->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Role inti aplikasi tidak dihapus saat rollback.
    }
};
