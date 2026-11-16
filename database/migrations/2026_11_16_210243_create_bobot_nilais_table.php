<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bobot_nilais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajarans')->cascadeOnDelete();
            $table->string('semester');
            $table->string('tahun_ajaran');
            $table->unsignedTinyInteger('bobot_formatif')->default(10);
            $table->unsignedTinyInteger('bobot_sumatif')->default(15);
            $table->unsignedTinyInteger('bobot_asts')->default(20);
            $table->unsignedTinyInteger('bobot_asas')->default(35);
            $table->unsignedTinyInteger('bobot_kehadiran')->default(5);
            $table->unsignedTinyInteger('bobot_sikap')->default(5);
            $table->unsignedTinyInteger('bobot_keaktifan')->default(10);
            $table->timestamps();

            $table->unique(
                ['mata_pelajaran_id', 'semester', 'tahun_ajaran'],
                'bobot_nilai_unique',
            );
        });

        $this->salinBobotRaporTerakhir();
    }

    /**
     * Bobot yang sudah diatur guru pada rapor lama dipakai sebagai bobot awal
     * agar nilai yang sudah tercatat tidak berubah saat fitur ini dirilis.
     */
    private function salinBobotRaporTerakhir(): void
    {
        $now = now();

        $rows = DB::table('e_rapors')
            ->select([
                'mata_pelajaran_id',
                'semester',
                'tahun_ajaran',
                'bobot_formatif',
                'bobot_sumatif',
                'bobot_asts',
                'bobot_asas',
                'bobot_kehadiran',
                'bobot_sikap',
                'bobot_keaktifan',
            ])
            ->whereNotNull('mata_pelajaran_id')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (object $row): string => $row->mata_pelajaran_id.'-'.$row->semester.'-'.$row->tahun_ajaran);

        foreach ($rows as $row) {
            DB::table('bobot_nilais')->insertOrIgnore([
                'mata_pelajaran_id' => $row->mata_pelajaran_id,
                'semester' => $row->semester,
                'tahun_ajaran' => $row->tahun_ajaran,
                'bobot_formatif' => $row->bobot_formatif,
                'bobot_sumatif' => $row->bobot_sumatif,
                'bobot_asts' => $row->bobot_asts,
                'bobot_asas' => $row->bobot_asas,
                'bobot_kehadiran' => $row->bobot_kehadiran,
                'bobot_sikap' => $row->bobot_sikap,
                'bobot_keaktifan' => $row->bobot_keaktifan,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bobot_nilais');
    }
};
