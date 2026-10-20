<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asesmen_formatifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->nullOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajarans')->nullOnDelete();
            $table->foreignId('pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revisi')->default(1);
            $table->string('semester');
            $table->string('tahun_ajaran');
            $table->string('nama_guru');
            $table->string('nama_kelas');
            $table->string('nama_mapel');
            $table->timestamp('dicatat_pada');
            $table->timestamps();

            $table->unique(['guru_id', 'kelas_id', 'mata_pelajaran_id', 'semester', 'tahun_ajaran'], 'asesmen_formatif_unique');
            $table->index(['pencatat_id', 'semester', 'tahun_ajaran'], 'asesmen_formatif_period_index');
        });

        Schema::create('asesmen_formatif_nilais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asesmen_formatif_id')->constrained('asesmen_formatifs')->cascadeOnDelete();
            $table->foreignId('murid_id')->nullable()->constrained('murids')->nullOnDelete();
            $table->foreignId('lingkup_materi_id')->nullable()->constrained('lingkup_materis')->nullOnDelete();
            $table->foreignId('tujuan_pembelajaran_id')->nullable()->constrained('tujuan_pembelajarans')->nullOnDelete();
            $table->string('nama_murid');
            $table->string('nis')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->text('lingkup_materi');
            $table->string('tujuan_pembelajaran', 100);
            $table->unsignedTinyInteger('nilai');
            $table->timestamps();

            $table->unique(['asesmen_formatif_id', 'murid_id', 'tujuan_pembelajaran_id'], 'asesmen_nilai_unique');
            $table->index(['asesmen_formatif_id', 'murid_id'], 'asesmen_nilai_siswa_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asesmen_formatif_nilais');
        Schema::dropIfExists('asesmen_formatifs');
    }
};
