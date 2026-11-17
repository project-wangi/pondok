<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bobot_nilais', function (Blueprint $table): void {
            $table->dropForeign(['mata_pelajaran_id']);
            $table->dropUnique('bobot_nilai_unique');
        });

        Schema::table('bobot_nilais', function (Blueprint $table): void {
            $table->foreignId('mata_pelajaran_id')->nullable()->change();
        });

        $this->rapikanBobotPerPeriode();

        Schema::table('bobot_nilais', function (Blueprint $table): void {
            $table->unique(['semester', 'tahun_ajaran'], 'bobot_nilai_periode_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bobot_nilais', function (Blueprint $table): void {
            $table->dropUnique('bobot_nilai_periode_unique');
        });

        DB::table('bobot_nilais')->whereNull('mata_pelajaran_id')->delete();

        Schema::table('bobot_nilais', function (Blueprint $table): void {
            $table->foreignId('mata_pelajaran_id')->nullable(false)->change();
            $table->foreign('mata_pelajaran_id')->references('id')->on('mata_pelajarans')->cascadeOnDelete();
            $table->unique(
                ['mata_pelajaran_id', 'semester', 'tahun_ajaran'],
                'bobot_nilai_unique',
            );
        });
    }

    /**
     * Bobot kini berlaku untuk semua mata pelajaran. Baris per mata pelajaran
     * lama diringkas menjadi satu baris global memakai bobot yang terakhir diubah.
     */
    private function rapikanBobotPerPeriode(): void
    {
        $periode = DB::table('bobot_nilais')
            ->select(['semester', 'tahun_ajaran'])
            ->distinct()
            ->get();

        foreach ($periode as $row) {
            $terakhir = DB::table('bobot_nilais')
                ->where('semester', $row->semester)
                ->where('tahun_ajaran', $row->tahun_ajaran)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            if ($terakhir === null) {
                continue;
            }

            DB::table('bobot_nilais')
                ->where('semester', $row->semester)
                ->where('tahun_ajaran', $row->tahun_ajaran)
                ->delete();

            DB::table('bobot_nilais')->insert([
                'mata_pelajaran_id' => null,
                'semester' => $row->semester,
                'tahun_ajaran' => $row->tahun_ajaran,
                'bobot_formatif' => $terakhir->bobot_formatif,
                'bobot_sumatif' => $terakhir->bobot_sumatif,
                'bobot_asts' => $terakhir->bobot_asts,
                'bobot_asas' => $terakhir->bobot_asas,
                'bobot_kehadiran' => $terakhir->bobot_kehadiran,
                'bobot_sikap' => $terakhir->bobot_sikap,
                'bobot_keaktifan' => $terakhir->bobot_keaktifan,
                'created_at' => $terakhir->created_at ?? now(),
                'updated_at' => $terakhir->updated_at ?? now(),
            ]);
        }
    }
};
