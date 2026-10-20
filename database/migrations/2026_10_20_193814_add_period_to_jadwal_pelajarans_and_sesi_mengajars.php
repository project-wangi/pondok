<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = CarbonImmutable::now();
        $startYear = $now->month >= 7 ? $now->year : $now->year - 1;
        $semester = $now->month >= 7 ? 'Ganjil' : 'Genap';
        $tahunAjaran = $startYear.'/'.($startYear + 1);

        Schema::table('jadwal_pelajarans', function (Blueprint $table) use ($semester, $tahunAjaran): void {
            $table->string('semester')->default($semester)->after('kelas_id');
            $table->string('tahun_ajaran')->default($tahunAjaran)->after('semester');
            $table->index(['semester', 'tahun_ajaran']);
        });

        Schema::table('sesi_mengajars', function (Blueprint $table) use ($semester, $tahunAjaran): void {
            $table->string('semester')->default($semester)->after('tanggal');
            $table->string('tahun_ajaran')->default($tahunAjaran)->after('semester');
            $table->index(['pencatat_id', 'semester', 'tahun_ajaran', 'tanggal'], 'sesi_mengajars_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_pelajarans', function (Blueprint $table): void {
            $table->dropIndex(['semester', 'tahun_ajaran']);
            $table->dropColumn(['semester', 'tahun_ajaran']);
        });

        Schema::table('sesi_mengajars', function (Blueprint $table): void {
            $table->dropIndex('sesi_mengajars_period_index');
            $table->dropColumn(['semester', 'tahun_ajaran']);
        });
    }
};
