<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_mengajars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_pelajaran_id')->nullable()->constrained('jadwal_pelajarans')->nullOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->nullOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revisi')->default(1);
            $table->date('tanggal');
            $table->string('nama_guru');
            $table->string('nama_kelas');
            $table->string('nama_mapel');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->timestamp('dicatat_pada');
            $table->timestamps();

            $table->unique(['jadwal_pelajaran_id', 'tanggal']);
            $table->index(['pencatat_id', 'tanggal']);
        });

        Schema::create('presensi_murids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_mengajar_id')->constrained('sesi_mengajars')->cascadeOnDelete();
            $table->foreignId('murid_id')->nullable()->constrained('murids')->nullOnDelete();
            $table->string('nama_murid');
            $table->string('nis')->nullable();
            $table->string('status');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['sesi_mengajar_id', 'murid_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi_murids');
        Schema::dropIfExists('sesi_mengajars');
    }
};
