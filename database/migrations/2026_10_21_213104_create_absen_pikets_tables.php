<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absen_pikets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->nullOnDelete();
            $table->foreignId('pencatat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revisi')->default(1);
            $table->date('tanggal');
            $table->string('nama_guru');
            $table->string('nama_kelas');
            $table->timestamp('dicatat_pada');
            $table->timestamps();

            $table->unique(['kelas_id', 'tanggal'], 'absen_piket_class_unique');
            $table->index(['pencatat_id', 'tanggal'], 'absen_piket_period_index');
        });

        Schema::create('absen_piket_murids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absen_piket_id')->constrained('absen_pikets')->cascadeOnDelete();
            $table->foreignId('murid_id')->nullable()->constrained('murids')->nullOnDelete();
            $table->string('nama_murid');
            $table->string('nis')->nullable();
            $table->string('masuk', 20)->nullable();
            $table->string('pulang', 20)->nullable();
            $table->timestamps();

            $table->unique(['absen_piket_id', 'murid_id'], 'absen_piket_murid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absen_piket_murids');
        Schema::dropIfExists('absen_pikets');
    }
};
