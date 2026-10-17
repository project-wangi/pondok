<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary()->default(1);
            $table->string('school_name');
            $table->string('tagline');
            $table->string('hero_title');
            $table->text('hero_description');
            $table->string('hero_image')->nullable();
            $table->string('logo')->nullable();
            $table->string('about_title');
            $table->text('about_body');
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('registration_label');
            $table->string('registration_url', 2048)->nullable();
            $table->json('shortcuts')->nullable();
            $table->json('stats')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
