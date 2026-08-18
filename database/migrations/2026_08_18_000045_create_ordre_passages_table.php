<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordre_passages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_notation_id')->constrained('config_notations')->cascadeOnDelete();
            $table->foreignUuid('inscription_id')->constrained('inscriptions');

            // Le kata est choisi par tour (Art. 6.1 WKF).
            $table->foreignUuid('kata_id')->nullable()->constrained('katas')->nullOnDelete();

            // Un combat Kata (Aka/Ao) donne lieu à un ou deux OrdrePassage par
            // camp (deux pour les finales par équipe : Kata puis Bunkai —
            // Art. 3.5.4/5.4.3 WKF), reliés au même combat pour le vote
            // majoritaire des juges.
            $table->foreignUuid('combat_id')->nullable()->constrained('combats')->nullOnDelete();
            // null pour un combat individuel ; 'kata' ou 'bunkai' pour les
            // deux prestations d'une finale d'équipe.
            $table->string('phase')->nullable();
            // Faute Bunkai constatée par le superviseur (Jodan Kani Basami,
            // inconscience simulée >2s, non-relève sous 2s — Art. 5.7/5.8).
            $table->boolean('disqualifie_bunkai')->default(false);

            $table->integer('ordre'); // 1, 2, 3... pour la file d'attente
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
            $table->index(['config_notation_id', 'status']);
            $table->unique(['combat_id', 'inscription_id', 'phase'], 'ordre_passages_combat_inscription_phase_unique');
            $table->unique(['config_notation_id', 'ordre']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('ordre_passages');
    }
};
