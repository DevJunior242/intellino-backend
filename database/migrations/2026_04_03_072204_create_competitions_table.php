<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('evenement_id')->constrained('evenements')->cascadeOnDelete();
            $table->foreignUuid('niveau_id')->constrained('niveaux_competitions')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignUuid('disciplineleague_id')->constrained('disciplineleagues')->cascadeOnDelete();
            $table->tinyInteger('statut');
            $table->dateTime('heure_debut_prevu')->nullable();

            // Exemple : 2026-04-03 20:00:00
            $table->dateTime('heure_fin_prevue')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
