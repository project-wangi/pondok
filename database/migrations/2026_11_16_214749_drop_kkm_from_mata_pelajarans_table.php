<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('mata_pelajarans', 'kkm')) {
            return;
        }

        Schema::table('mata_pelajarans', function (Blueprint $table) {
            $table->dropColumn('kkm');
        });
    }

    public function down(): void
    {
        Schema::table('mata_pelajarans', function (Blueprint $table) {
            $table->unsignedTinyInteger('kkm')->default(75);
        });
    }
};
