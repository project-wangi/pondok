<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lingkup_materis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('gurus')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajarans')->cascadeOnDelete();
            $table->string('semester');
            $table->string('tahun_ajaran');
            $table->unsignedSmallInteger('urutan');
            $table->text('materi');
            $table->timestamps();

            $table->unique(['guru_id', 'mata_pelajaran_id', 'semester', 'tahun_ajaran', 'urutan'], 'lingkup_materi_unique');
            $table->index(['guru_id', 'semester', 'tahun_ajaran'], 'lingkup_materi_period_index');
        });

        Schema::create('tujuan_pembelajarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lingkup_materi_id')->constrained('lingkup_materis')->cascadeOnDelete();
            $table->unsignedTinyInteger('urutan');
            $table->string('teks', 100);
            $table->timestamps();

            $table->unique(['lingkup_materi_id', 'urutan'], 'tujuan_pembelajaran_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tujuan_pembelajarans');
        Schema::dropIfExists('lingkup_materis');
    }
};
