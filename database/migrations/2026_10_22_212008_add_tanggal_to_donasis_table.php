<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donasis', function (Blueprint $table) {
            $table->date('tanggal')->nullable()->after('guru_id');
        });

        DB::table('donasis')->whereNull('tanggal')->update(['tanggal' => now()->toDateString()]);
    }

    public function down(): void
    {
        Schema::table('donasis', function (Blueprint $table) {
            $table->dropColumn('tanggal');
        });
    }
};
