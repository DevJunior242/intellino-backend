<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('evenement_id')->constrained('evenements')->cascadeOnDelete();
            $table->foreignUuid('niveau_id')->constrained('niveaux_competitions')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignUuid('sub_discipline_id')->constrained('sub_disciplines')->cascadeOnDelete();
            $table->tinyInteger('status')->default(0); // 0 = En attente, 1 = En cours, 2 = Terminé
            $table->dateTime('heure_debut_prevu')->nullable();
            $table->dateTime('heure_fin_prevue')->nullable();
            $table->index(['status', 'heure_debut_prevu', 'heure_fin_prevue']);
            // Kata par équipe (3-4 athlètes, Bunkai en finale — Art. 3.5 WKF).
            $table->boolean('est_equipe')->default(false);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
