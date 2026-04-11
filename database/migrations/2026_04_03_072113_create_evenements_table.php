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
        Schema::create('evenements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom'); // ex: "Open de Karaté 2026"
            $table->uuidMorphs('organisateur');
            $table->string('lieu'); // ex: "Ouagadougou", "Bobo-Dioulasso"
            $table->date('date_debut'); // ex: 2026-05-15
            $table->date('date_fin');
            $table->tinyInteger('statut'); // 0 = En attente, 1 = En cours, 2 = Terminé
            $table->index(['statut', 'date_debut', 'date_fin']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evenements');
    }
};
