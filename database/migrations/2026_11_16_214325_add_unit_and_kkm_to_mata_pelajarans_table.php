<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mata_pelajarans', function (Blueprint $table) {
            $table->string('unit', 10)->nullable();
            $table->unsignedTinyInteger('kkm')->default(75);
        });
    }

    public function down(): void
    {
        Schema::table('mata_pelajarans', function (Blueprint $table) {
            $table->dropColumn(['unit', 'kkm']);
        });
    }
};
