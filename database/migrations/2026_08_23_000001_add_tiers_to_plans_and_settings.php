<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;


return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('organisateur_type')->nullable()->after('name');
            $table->unsignedInteger('min_users')->default(0)->after('amount');
            $table->unsignedInteger('max_users')->nullable()->after('min_users');
        });

        // Réglages globaux de la plateforme (clé/valeur) — commence avec la
        // réduction annuelle, éditable par le super admin sans redéploiement.
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['organisateur_type', 'min_users', 'max_users']);
        });
    }
};
