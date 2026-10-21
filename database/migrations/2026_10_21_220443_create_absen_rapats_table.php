<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absen_rapats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_rapat_id')->constrained('jadwal_rapats')->cascadeOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_guru');
            $table->string('agenda');
            $table->date('tanggal');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->string('foto');
            $table->timestamp('dicatat_pada');
            $table->timestamps();

            $table->unique(['jadwal_rapat_id', 'guru_id'], 'absen_rapat_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absen_rapats');
    }
};
