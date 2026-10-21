<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lingkup_materis', function (Blueprint $table) {
            $table->foreignId('kelas_id')->nullable()->after('guru_id')->constrained('kelas')->nullOnDelete();
        });

        Schema::table('lingkup_materis', function (Blueprint $table) {
            $table->dropUnique('lingkup_materi_unique');
        });

        $this->backfillClasses();

        Schema::table('lingkup_materis', function (Blueprint $table) {
            $table->unique(
                ['guru_id', 'kelas_id', 'mata_pelajaran_id', 'semester', 'tahun_ajaran', 'urutan'],
                'lingkup_materi_class_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('lingkup_materis', function (Blueprint $table) {
            $table->dropUnique('lingkup_materi_class_unique');
        });

        $this->removeClassDuplicates();

        Schema::table('lingkup_materis', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kelas_id');
            $table->unique(
                ['guru_id', 'mata_pelajaran_id', 'semester', 'tahun_ajaran', 'urutan'],
                'lingkup_materi_unique',
            );
        });
    }

    /**
     * Salin lingkup materi lama (tanpa kelas) ke setiap kelas yang diajar.
     */
    private function backfillClasses(): void
    {
        $materials = DB::table('lingkup_materis')->whereNull('kelas_id')->orderBy('id')->get();

        foreach ($materials as $material) {
            $classIds = DB::table('jadwal_pelajarans')
                ->where('guru_id', $material->guru_id)
                ->where('mata_pelajaran', (string) $material->mata_pelajaran_id)
                ->where('semester', $material->semester)
                ->where('tahun_ajaran', $material->tahun_ajaran)
                ->whereNotNull('kelas_id')
                ->distinct()
                ->orderBy('kelas_id')
                ->pluck('kelas_id')
                ->all();

            if ($classIds === []) {
                continue;
            }

            DB::table('lingkup_materis')->where('id', $material->id)->update(['kelas_id' => array_shift($classIds)]);

            foreach ($classIds as $classId) {
                $copyId = DB::table('lingkup_materis')->insertGetId([
                    'guru_id' => $material->guru_id,
                    'kelas_id' => $classId,
                    'mata_pelajaran_id' => $material->mata_pelajaran_id,
                    'semester' => $material->semester,
                    'tahun_ajaran' => $material->tahun_ajaran,
                    'urutan' => $material->urutan,
                    'materi' => $material->materi,
                    'created_at' => $material->created_at,
                    'updated_at' => $material->updated_at,
                ]);

                $purposes = DB::table('tujuan_pembelajarans')
                    ->where('lingkup_materi_id', $material->id)
                    ->orderBy('urutan')
                    ->get();

                foreach ($purposes as $purpose) {
                    DB::table('tujuan_pembelajarans')->insert([
                        'lingkup_materi_id' => $copyId,
                        'urutan' => $purpose->urutan,
                        'teks' => $purpose->teks,
                        'created_at' => $purpose->created_at,
                        'updated_at' => $purpose->updated_at,
                    ]);
                }
            }
        }
    }

    /**
     * Sisakan satu baris per guru, mapel, periode, dan urutan sebelum indeks lama dipulihkan.
     */
    private function removeClassDuplicates(): void
    {
        $groups = DB::table('lingkup_materis')
            ->select('guru_id', 'mata_pelajaran_id', 'semester', 'tahun_ajaran', 'urutan')
            ->selectRaw('MIN(id) as keep_id')
            ->groupBy('guru_id', 'mata_pelajaran_id', 'semester', 'tahun_ajaran', 'urutan')
            ->get();

        foreach ($groups as $group) {
            DB::table('lingkup_materis')
                ->where('guru_id', $group->guru_id)
                ->where('mata_pelajaran_id', $group->mata_pelajaran_id)
                ->where('semester', $group->semester)
                ->where('tahun_ajaran', $group->tahun_ajaran)
                ->where('urutan', $group->urutan)
                ->where('id', '!=', $group->keep_id)
                ->delete();
        }
    }
};
