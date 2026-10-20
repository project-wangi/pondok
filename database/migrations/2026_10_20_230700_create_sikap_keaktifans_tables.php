<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikap_keaktifans', function (Blueprint $table) {
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

            $table->unique(['guru_id', 'kelas_id', 'mata_pelajaran_id', 'semester', 'tahun_ajaran'], 'sikap_keaktifan_unique');
            $table->index(['pencatat_id', 'semester', 'tahun_ajaran'], 'sikap_keaktifan_period_index');
        });

        Schema::create('sikap_keaktifan_nilais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sikap_keaktifan_id')->constrained('sikap_keaktifans')->cascadeOnDelete();
            $table->foreignId('murid_id')->nullable()->constrained('murids')->nullOnDelete();
            $table->string('nama_murid');
            $table->string('nis')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('sikap', 1)->nullable();
            $table->string('keaktifan', 1)->nullable();
            $table->timestamps();

            $table->unique(['sikap_keaktifan_id', 'murid_id'], 'sikap_nilai_unique');
            $table->index(['sikap_keaktifan_id', 'murid_id'], 'sikap_nilai_siswa_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikap_keaktifan_nilais');
        Schema::dropIfExists('sikap_keaktifans');
    }
};
