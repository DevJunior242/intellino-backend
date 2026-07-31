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
        Schema::table('ordre_passages', function (Blueprint $table) {
            // Un combat Kata (Aka/Ao) donne lieu à un ou deux OrdrePassage par
            // camp (deux pour les finales par équipe : Kata puis Bunkai —
            // Art. 3.5.4/5.4.3 WKF), reliés au même combat pour le vote
            // majoritaire des juges.
            $table->foreignUuid('combat_id')
                ->nullable()
                ->after('config_notation_id')
                ->constrained('combats')
                ->nullOnDelete();

            // null pour un combat individuel ; 'kata' ou 'bunkai' pour les
            // deux prestations d'une finale d'équipe.
            $table->string('phase')->nullable()->after('combat_id');

            // Faute Bunkai constatée par le superviseur (Jodan Kani Basami,
            // inconscience simulée >2s, non-relève sous 2s — Art. 5.7/5.8).
            $table->boolean('disqualifie_bunkai')->default(false)->after('phase');

            // L'ancienne contrainte limitait un athlète à UN SEUL passage par
            // tatami — incompatible avec un duel (un passage par combat).
            $table->dropUnique(['config_notation_id', 'inscription_id']);
            $table->unique(
                ['combat_id', 'inscription_id', 'phase'],
                'ordre_passages_combat_inscription_phase_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordre_passages', function (Blueprint $table) {
            $table->dropUnique('ordre_passages_combat_inscription_phase_unique');
            $table->dropForeign(['combat_id']);
            $table->dropColumn(['combat_id', 'phase', 'disqualifie_bunkai']);

            $table->unique(['config_notation_id', 'inscription_id']);
        });
    }
};
