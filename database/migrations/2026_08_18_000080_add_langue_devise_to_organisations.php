<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Champs stockés pour préparer une future personnalisation langue/devise par
// organisation — non encore appliqués à l'affichage ailleurs dans l'app
// (qui reste en français/XOF en dur pour l'instant), voir OrganisationController.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['clubs', 'leagues', 'federations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('langue', 5)->default('fr');
                $blueprint->string('devise', 10)->default('XOF');
            });
        }
    }

    public function down(): void
    {
        foreach (['clubs', 'leagues', 'federations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['langue', 'devise']);
            });
        }
    }
};
