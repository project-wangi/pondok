<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absen_peminatans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_peminatan_id')->nullable()->constrained('jenis_peminatans')->nullOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->nullOnDelete();
            $table->foreignId('pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revisi')->default(1);
            $table->date('tanggal');
            $table->string('nama_guru');
            $table->string('nama_peminatan');
            $table->timestamp('dicatat_pada');
            $table->timestamps();

            $table->unique(['jenis_peminatan_id', 'tanggal'], 'absen_peminatan_unique');
            $table->index(['pencatat_id', 'tanggal'], 'absen_peminatan_period_index');
        });

        Schema::create('absen_peminatan_murids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absen_peminatan_id')->constrained('absen_peminatans')->cascadeOnDelete();
            $table->foreignId('murid_id')->nullable()->constrained('murids')->nullOnDelete();
            $table->string('nama_murid');
            $table->string('nis')->nullable();
            $table->string('nama_kelas')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['absen_peminatan_id', 'murid_id'], 'absen_peminatan_murid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absen_peminatan_murids');
        Schema::dropIfExists('absen_peminatans');
    }
};
