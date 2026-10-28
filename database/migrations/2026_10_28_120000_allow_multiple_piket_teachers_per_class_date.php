<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absen_pikets', function (Blueprint $table) {
            $table->unique(['kelas_id', 'tanggal', 'guru_id'], 'absen_piket_class_teacher_unique');
        });

        Schema::table('absen_pikets', function (Blueprint $table) {
            $table->dropUnique('absen_piket_class_unique');
        });
    }

    public function down(): void
    {
        // Satu kelas hanya boleh punya satu catatan per tanggal pada struktur lama.
        DB::table('absen_pikets')
            ->whereNotNull('kelas_id')
            ->select('kelas_id', 'tanggal')
            ->selectRaw('MIN(id) as keep_id')
            ->groupBy('kelas_id', 'tanggal')
            ->get()
            ->each(function (object $group): void {
                DB::table('absen_pikets')
                    ->where('kelas_id', $group->kelas_id)
                    ->whereDate('tanggal', $group->tanggal)
                    ->where('id', '!=', $group->keep_id)
                    ->delete();
            });

        Schema::table('absen_pikets', function (Blueprint $table) {
            $table->unique(['kelas_id', 'tanggal'], 'absen_piket_class_unique');
        });

        Schema::table('absen_pikets', function (Blueprint $table) {
            $table->dropUnique('absen_piket_class_teacher_unique');
        });
    }
};
