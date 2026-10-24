<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai_ujians', function (Blueprint $table) {
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

            $table->unique(
                ['guru_id', 'kelas_id', 'mata_pelajaran_id', 'semester', 'tahun_ajaran'],
                'nilai_ujian_unique',
            );
            $table->index(['pencatat_id', 'semester', 'tahun_ajaran'], 'nilai_ujian_period_index');
        });

        Schema::create('nilai_ujian_komponens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nilai_ujian_id')->constrained('nilai_ujians')->cascadeOnDelete();
            $table->string('nama', 100);
            $table->unsignedSmallInteger('urutan');
            $table->timestamps();

            $table->unique(['nilai_ujian_id', 'urutan'], 'nilai_ujian_komponen_unique');
        });

        Schema::create('nilai_ujian_nilais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nilai_ujian_komponen_id')->constrained('nilai_ujian_komponens')->cascadeOnDelete();
            $table->foreignId('murid_id')->nullable()->constrained('murids')->nullOnDelete();
            $table->string('nama_murid');
            $table->string('nis')->nullable();
            $table->unsignedTinyInteger('nilai')->nullable();
            $table->string('catatan', 500)->nullable();
            $table->timestamps();

            $table->unique(['nilai_ujian_komponen_id', 'murid_id'], 'nilai_ujian_nilai_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_ujian_nilais');
        Schema::dropIfExists('nilai_ujian_komponens');
        Schema::dropIfExists('nilai_ujians');
    }
};
