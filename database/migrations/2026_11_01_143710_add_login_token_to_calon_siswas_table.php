<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calon_siswas', function (Blueprint $table) {
            $table->string('login_token', 64)->nullable()->unique()->after('status');
            $table->timestamp('login_token_expires_at')->nullable()->after('login_token');
        });
    }

    public function down(): void
    {
        Schema::table('calon_siswas', function (Blueprint $table) {
            $table->dropUnique(['login_token']);
            $table->dropColumn(['login_token', 'login_token_expires_at']);
        });
    }
};
