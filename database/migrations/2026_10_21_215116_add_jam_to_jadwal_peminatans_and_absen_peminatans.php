<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_peminatans', function (Blueprint $table) {
            $table->time('jam_mulai')->default('07:00:00')->after('hari');
            $table->time('jam_selesai')->default('09:00:00')->after('jam_mulai');
        });

        Schema::table('absen_peminatans', function (Blueprint $table) {
            $table->time('jam_mulai')->nullable()->after('tanggal');
            $table->time('jam_selesai')->nullable()->after('jam_mulai');
        });
    }

    public function down(): void
    {
        Schema::table('absen_peminatans', function (Blueprint $table) {
            $table->dropColumn(['jam_mulai', 'jam_selesai']);
        });

        Schema::table('jadwal_peminatans', function (Blueprint $table) {
            $table->dropColumn(['jam_mulai', 'jam_selesai']);
        });
    }
};
