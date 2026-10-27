<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_kepribadians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('murid_id')->constrained('murids')->cascadeOnDelete();
            $table->string('semester');
            $table->string('tahun_ajaran');
            $table->unsignedTinyInteger('kelakuan')->nullable();
            $table->unsignedTinyInteger('kerajinan')->nullable();
            $table->unsignedTinyInteger('kebersihan')->nullable();
            $table->unsignedTinyInteger('kerapian')->nullable();
            $table->unsignedTinyInteger('kedisiplinan')->nullable();
            $table->timestamps();

            $table->unique(['murid_id', 'semester', 'tahun_ajaran'], 'rapor_kepribadian_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_kepribadians');
    }
};
