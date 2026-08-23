<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Chaque Ligue (affiliée ou non) et chaque Fédération gère désormais ses
// propres catégories de façon indépendante (plus d'héritage/blocage entre
// ligue affiliée et fédération) — les catégories ont donc besoin de leur
// propre organisateur, comme sub_disciplines, plutôt que d'être identifiées
// uniquement via la saison (qui elle reste hiérarchique).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->uuid('organisateur_id')->nullable()->after('id');
            $table->string('organisateur_type')->nullable()->after('organisateur_id');
        });

        // Backfill : toutes les catégories existantes ont été créées avant
        // ce changement, donc systématiquement par l'organisateur de la
        // saison à laquelle elles sont rattachées.
        DB::statement(
            'UPDATE categories
             JOIN saisons ON saisons.id = categories.saison_id
             SET categories.organisateur_id = saisons.organisateur_id,
                 categories.organisateur_type = saisons.organisateur_type
             WHERE categories.organisateur_id IS NULL'
        );

        // L'ancienne contrainte (nom, sexe, saison_id) empêchait une ligue et
        // sa fédération de définir chacune une catégorie de même nom pour la
        // même saison — or elles partagent la même saison (héritée) tout en
        // gérant des catégories désormais indépendantes.
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['nom', 'sexe', 'saison_id']);
            $table->unique(['nom', 'sexe', 'saison_id', 'organisateur_id', 'organisateur_type'], 'categories_nom_sexe_saison_organisateur_unique');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_nom_sexe_saison_organisateur_unique');
            $table->unique(['nom', 'sexe', 'saison_id']);
            $table->dropColumn(['organisateur_id', 'organisateur_type']);
        });
    }
};
