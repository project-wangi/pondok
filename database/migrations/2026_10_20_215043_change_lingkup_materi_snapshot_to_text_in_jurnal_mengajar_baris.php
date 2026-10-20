<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The source material allows 500 characters, so the snapshot must not be capped at 255.
        Schema::table('jurnal_mengajar_baris', function (Blueprint $table): void {
            $table->text('lingkup_materi')->change();
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_mengajar_baris', function (Blueprint $table): void {
            $table->string('lingkup_materi')->change();
        });
    }
};
