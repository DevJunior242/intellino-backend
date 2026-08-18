<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('organisateur');
            $table->foreignUuid('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignUuid('athlete_id')->constrained('students')->cascadeOnDelete();

            // Le kata est choisi par tour (Art. 6.1 WKF), pas une fois pour
            // toute la compétition : rattaché au passage (ordre_passages),
            // pas à l'inscription.
            $table->foreignUuid('kata_id')->nullable()->constrained('katas')->nullOnDelete();

            // Champs Poids (essentiels même pour le Kata pour le dossier complet)
            $table->decimal('poids_declare', 5, 2)->nullable(); // Saisi par le club
            $table->decimal('poids_officiel', 5, 2)->nullable(); // Saisi par la Ligue le jour J

            $table->tinyInteger('status')->default(0)->index(); // 0 = En attente, 1 = Validé, 2 = Échoué

            // Ordre de passage dans la compétition
            $table->integer('ordre_passage')->nullable();

            // Statut du passage avec tinyInteger
            $table->tinyInteger('statut_passage')->default(0);

            // Index pour récupérer facilement le suivant
            $table->index(['competition_id', 'ordre_passage']);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};
