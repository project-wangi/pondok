<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nilai_ujian_komponens', function (Blueprint $table) {
            $table->timestamp('dicatat_pada')->nullable()->after('urutan');
        });

        // Komponen lama dianggap terisi saat terakhir disimpan.
        DB::table('nilai_ujian_komponens')
            ->whereNull('dicatat_pada')
            ->update(['dicatat_pada' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('nilai_ujian_komponens', function (Blueprint $table) {
            $table->dropColumn('dicatat_pada');
        });
    }
};
