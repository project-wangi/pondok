<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absen_pikets', function (Blueprint $table) {
            $table->timestamp('masuk_pada')->nullable()->after('dicatat_pada');
            $table->timestamp('pulang_pada')->nullable()->after('masuk_pada');
        });

        // Data lama hanya menyimpan satu waktu pencatatan, jadi waktu masuk dan
        // pulang diisi dari waktu tersebut agar dashboard tidak kosong.
        DB::statement(
            'UPDATE absen_pikets SET masuk_pada = dicatat_pada WHERE masuk_pada IS NULL AND EXISTS ('
            .'SELECT 1 FROM absen_piket_murids WHERE absen_piket_murids.absen_piket_id = absen_pikets.id '
            .'AND absen_piket_murids.masuk IS NOT NULL)',
        );

        DB::statement(
            'UPDATE absen_pikets SET pulang_pada = dicatat_pada WHERE pulang_pada IS NULL AND EXISTS ('
            .'SELECT 1 FROM absen_piket_murids WHERE absen_piket_murids.absen_piket_id = absen_pikets.id '
            .'AND absen_piket_murids.pulang IS NOT NULL)',
        );
    }

    public function down(): void
    {
        Schema::table('absen_pikets', function (Blueprint $table) {
            $table->dropColumn(['masuk_pada', 'pulang_pada']);
        });
    }
};
