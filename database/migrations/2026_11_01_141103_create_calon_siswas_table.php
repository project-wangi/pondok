<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calon_siswas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nomor_pendaftaran')->nullable()->unique();
            $table->string('nama');
            $table->string('jenis_kelamin');
            $table->string('email')->unique();
            $table->string('nohp', 20);
            $table->unsignedTinyInteger('nilai_tes')->nullable();
            $table->string('status', 20)->default('Menunggu');
            $table->text('catatan')->nullable();
            $table->foreignId('murid_id')->nullable()->unique()->constrained('murids')->nullOnDelete();
            $table->timestamp('diterima_pada')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calon_siswas');
    }
};
