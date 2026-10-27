<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gurus', function (Blueprint $table) {
            $table->foreignId('kelas_id')
                ->nullable()
                ->unique()
                ->after('user_id')
                ->constrained('kelas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gurus', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kelas_id');
        });
    }
};
