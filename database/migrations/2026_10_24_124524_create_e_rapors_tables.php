<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_rapors', function (Blueprint $table) {
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
            $table->unsignedTinyInteger('bobot_formatif')->default(10);
            $table->unsignedTinyInteger('bobot_sumatif')->default(15);
            $table->unsignedTinyInteger('bobot_asts')->default(20);
            $table->unsignedTinyInteger('bobot_asas')->default(35);
            $table->unsignedTinyInteger('bobot_kehadiran')->default(5);
            $table->unsignedTinyInteger('bobot_sikap')->default(5);
            $table->unsignedTinyInteger('bobot_keaktifan')->default(10);
            $table->unsignedTinyInteger('kkm')->default(75);
            $table->timestamp('dicatat_pada');
            $table->timestamps();

            $table->unique(
                ['guru_id', 'kelas_id', 'mata_pelajaran_id', 'semester', 'tahun_ajaran'],
                'e_rapor_unique',
            );
            $table->index(['pencatat_id', 'semester', 'tahun_ajaran'], 'e_rapor_period_index');
        });

        Schema::create('e_rapor_nilais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('e_rapor_id')->constrained('e_rapors')->cascadeOnDelete();
            $table->foreignId('murid_id')->nullable()->constrained('murids')->nullOnDelete();
            $table->string('nama_murid');
            $table->string('nis')->nullable();
            $table->unsignedTinyInteger('asts')->nullable();
            $table->unsignedTinyInteger('asas')->nullable();
            $table->timestamps();

            $table->unique(['e_rapor_id', 'murid_id'], 'e_rapor_nilai_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_rapor_nilais');
        Schema::dropIfExists('e_rapors');
    }
};
