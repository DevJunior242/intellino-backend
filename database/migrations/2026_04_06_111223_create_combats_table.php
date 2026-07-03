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

        Schema::create('combats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_notation_id')->constrained('config_notations')->cascadeOnDelete();

            // Les deux adversaires (liés à la table inscriptions)
            $table->foreignUuid('inscription_aka_id')->nullable()->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignUuid('inscription_ao_id')->nullable()->constrained('inscriptions')->cascadeOnDelete();
            // Nullable : car un combat de tableau final n'est pas forcément dans une poule
            $table->foreignUuid('poule_id')->nullable()->constrained('poules')->nullOnDelete();
            // Scores finaux (mis à jour à la fin du chrono)
            $table->integer('score_final_aka')->default(0);
            $table->integer('score_final_ao')->default(0);

            //Avantage "Senshu" (premier point marqué sans être simultané)
            $table->uuid('senshu_id')->nullable();

            $table->uuid('vainqueur_id')->nullable();
            $table->string('type_victoire')->nullable(); // Hantei, Kiken, Hansoku, Points
            $table->boolean('is_bye')->default(false);
            $table->string('etape'); // Finale, Demie, Poule_Match_1, etc.
            $table->tinyInteger('status')->default(0); // 0 = En attente, 1 = En cours, 2 = Terminé,3 = Forcé Hantei
            $table->uuid('source_aka_combat_id')->nullable();

            // Next combat (format éliminatoire seulement)
            $table->uuid('next_combat_id')->nullable();
            // Pas de FK directe car auto-référence
            $table->timestamp('yame_at')->nullable();      // moment du stop
            $table->timestamp('hajime_at')->nullable();    // moment du start/resume
            $table->integer('temps_ecoule')->default(0);            // Ordre d'exécution
            $table->integer('ordre')->default(0);
            $table->uuid('source_ao_combat_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('combats');
    }
};
