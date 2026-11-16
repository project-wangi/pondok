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
        Schema::table('jadwal_rapats', function (Blueprint $table) {
            $table->longText('notulensi')->nullable()->after('agenda');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_rapats', function (Blueprint $table) {
            $table->dropColumn('notulensi');
        });
    }
};
