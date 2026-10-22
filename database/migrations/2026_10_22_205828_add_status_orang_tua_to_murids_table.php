<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('murids', function (Blueprint $table) {
            $table->string('status_ayah', 20)->nullable()->after('nama_ayah');
            $table->string('status_ibu', 20)->nullable()->after('nama_ibu');
        });
    }

    public function down(): void
    {
        Schema::table('murids', function (Blueprint $table) {
            $table->dropColumn(['status_ayah', 'status_ibu']);
        });
    }
};
