<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('absen_gurus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->nullable()->constrained('gurus')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_guru');
            $table->date('tanggal');
            $table->timestamp('masuk_pada')->nullable();
            $table->timestamp('pulang_pada')->nullable();
            $table->timestamps();

            // Satu guru hanya punya satu catatan absen per hari.
            $table->unique(['guru_id', 'tanggal'], 'absen_guru_unique');

            // Dipakai rekap gaji: rentang tanggal tanpa filter guru.
            $table->index('tanggal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absen_gurus');
    }
};
