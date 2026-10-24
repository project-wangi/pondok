<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->json('programs')->nullable()->after('stats');
            $table->json('rankings')->nullable()->after('programs');
            $table->json('excellence_features')->nullable()->after('rankings');
            $table->json('partners')->nullable()->after('excellence_features');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn(['programs', 'rankings', 'excellence_features', 'partners']);
        });
    }
};
