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
        Schema::create('kata_teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // L'équipe est portée par une Inscription ordinaire (athlete_id
            // = capitaine désigné, uniquement pour satisfaire la contrainte
            // NOT NULL existante) — son identité réelle d'équipe vient d'ici.
            $table->foreignUuid('inscription_id')->unique()->constrained('inscriptions')->cascadeOnDelete();
            $table->string('nom');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kata_teams');
    }
};
