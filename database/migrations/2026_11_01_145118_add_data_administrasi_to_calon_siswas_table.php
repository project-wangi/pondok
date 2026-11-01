<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calon_siswas', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->after('nohp');
            $table->string('no_kk', 16)->nullable()->after('nik');
            $table->string('tempat_lahir')->nullable()->after('no_kk');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->string('agama', 20)->nullable()->after('tanggal_lahir');
            $table->text('alamat')->nullable()->after('agama');
            $table->string('nohp_siswa', 20)->nullable()->after('alamat');

            $table->string('nama_ayah')->nullable()->after('nohp_siswa');
            $table->string('status_ayah', 20)->nullable()->after('nama_ayah');
            $table->string('nik_ayah', 16)->nullable()->after('status_ayah');
            $table->string('pekerjaan_ayah')->nullable()->after('nik_ayah');
            $table->string('penghasilan_ayah', 50)->nullable()->after('pekerjaan_ayah');
            $table->string('nohp_ayah', 20)->nullable()->after('penghasilan_ayah');

            $table->string('nama_ibu')->nullable()->after('nohp_ayah');
            $table->string('status_ibu', 20)->nullable()->after('nama_ibu');
            $table->string('nik_ibu', 16)->nullable()->after('status_ibu');
            $table->string('pekerjaan_ibu')->nullable()->after('nik_ibu');
            $table->string('penghasilan_ibu', 50)->nullable()->after('pekerjaan_ibu');
            $table->string('nohp_ibu', 20)->nullable()->after('penghasilan_ibu');

            $table->string('foto')->nullable()->after('nohp_ibu');
            $table->string('ijazah')->nullable()->after('foto');
            $table->string('kk')->nullable()->after('ijazah');
            $table->string('kip')->nullable()->after('kk');
        });
    }

    public function down(): void
    {
        Schema::table('calon_siswas', function (Blueprint $table) {
            $table->dropColumn([
                'nik',
                'no_kk',
                'tempat_lahir',
                'tanggal_lahir',
                'agama',
                'alamat',
                'nohp_siswa',
                'nama_ayah',
                'status_ayah',
                'nik_ayah',
                'pekerjaan_ayah',
                'penghasilan_ayah',
                'nohp_ayah',
                'nama_ibu',
                'status_ibu',
                'nik_ibu',
                'pekerjaan_ibu',
                'penghasilan_ibu',
                'nohp_ibu',
                'foto',
                'ijazah',
                'kk',
                'kip',
            ]);
        });
    }
};
