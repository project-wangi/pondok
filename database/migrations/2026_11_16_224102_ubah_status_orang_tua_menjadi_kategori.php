<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nilai lama "Hidup"/"Meninggal" menggambarkan status, bukan kategori.
        // Keduanya dianggap orang tua kandung karena kolomnya tetap dipakai
        // untuk menyimpan kategori Kandung/Tiri.
        foreach (['murids', 'calon_siswas'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (['status_ayah', 'status_ibu'] as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->whereNotNull($column)
                    ->whereNotIn($column, ['Kandung', 'Tiri'])
                    ->update([$column => 'Kandung']);
            }
        }
    }

    public function down(): void
    {
        // Nilai lama tidak dapat dikembalikan setelah dikonversi.
    }
};
