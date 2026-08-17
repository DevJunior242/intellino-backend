<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marque quelle Ligue/Fédération a inscrit directement un athlète SANS
     * club (athlète indépendant) — un élève rattaché à un club n'en a pas
     * besoin, son organisateur se déduit déjà de club_students -> clubs ->
     * league_id -> federation_id. Sans ce marquage, un athlète indépendant
     * n'était retrouvable par aucune requête d'éligibilité aux épreuves.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->uuid('organisateur_id')->nullable()->after('user_id');
            $table->string('organisateur_type')->nullable()->after('organisateur_id');
            $table->index(['organisateur_type', 'organisateur_id']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['organisateur_type', 'organisateur_id']);
            $table->dropColumn(['organisateur_id', 'organisateur_type']);
        });
    }
};
