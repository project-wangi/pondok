<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donasis', function (Blueprint $table) {
            $table->index('guru_id', 'donasis_guru_id_index');
        });

        Schema::table('donasis', function (Blueprint $table) {
            $table->dropUnique(['guru_id']);
        });
    }

    public function down(): void
    {
        Schema::table('donasis', function (Blueprint $table) {
            $table->dropIndex('donasis_guru_id_index');
            $table->unique(['guru_id']);
        });
    }
};
