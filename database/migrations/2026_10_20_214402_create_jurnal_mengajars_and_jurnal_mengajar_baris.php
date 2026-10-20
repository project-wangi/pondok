<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_mengajars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_pelajaran_id')->nullable()->constrained('jadwal_pelajarans')->nullOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->nullOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajarans')->nullOnDelete();
            $table->foreignId('pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revisi')->default(1);
            $table->date('tanggal');
            $table->string('semester');
            $table->string('tahun_ajaran');
            $table->string('nama_guru');
            $table->string('nama_kelas');
            $table->string('nama_mapel');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->timestamp('dicatat_pada');
            $table->timestamps();

            $table->unique(['jadwal_pelajaran_id', 'tanggal'], 'jurnal_mengajar_unique');
            $table->index(['pencatat_id', 'tanggal'], 'jurnal_mengajar_pencatat_index');
            $table->index(['guru_id', 'semester', 'tahun_ajaran'], 'jurnal_mengajar_period_index');
        });

        Schema::create('jurnal_mengajar_baris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurnal_mengajar_id')->constrained('jurnal_mengajars')->cascadeOnDelete();
            $table->foreignId('lingkup_materi_id')->nullable()->constrained('lingkup_materis')->nullOnDelete();
            $table->foreignId('tujuan_pembelajaran_id')->nullable()->constrained('tujuan_pembelajarans')->nullOnDelete();
            $table->string('lingkup_materi');
            $table->string('tujuan_pembelajaran');
            $table->text('kegiatan');
            $table->text('catatan')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->unsignedSmallInteger('urutan');
            $table->timestamps();

            $table->unique(['jurnal_mengajar_id', 'urutan'], 'jurnal_mengajar_baris_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_mengajar_baris');
        Schema::dropIfExists('jurnal_mengajars');
    }
};
