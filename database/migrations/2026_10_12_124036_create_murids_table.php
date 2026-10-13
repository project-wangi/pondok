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
        Schema::create('murids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();

            // Data Pribadi Murid
            $table->string('foto')->nullable();
            $table->string('nama');
            $table->string('nisn', 10)->nullable();
            $table->string('nis', 30)->nullable();
            $table->string('nik')->nullable();
            $table->string('no_kk')->nullable();
            $table->string('email')->unique();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('jenis_kelamin');
            $table->string('agama')->nullable();
            $table->text('alamat')->nullable();

            // Data Orang Tua / Wali
            $table->string('nama_ayah')->nullable();
            $table->string('nik_ayah')->nullable();
            $table->string('pekerjaan_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('nik_ibu')->nullable();
            $table->string('pekerjaan_ibu')->nullable();
            $table->string('penghasilan_ayah')->nullable();
            $table->string('penghasilan_ibu')->nullable();
            $table->string('nohp_siswa')->nullable();
            $table->string('nohp_ayah')->nullable();
            $table->string('nohp_ibu')->nullable();

            // Dokumen (PDF / Gambar)
            $table->string('ijazah')->nullable();
            $table->string('kk')->nullable();
            $table->string('kip')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('murids');
    }
};
