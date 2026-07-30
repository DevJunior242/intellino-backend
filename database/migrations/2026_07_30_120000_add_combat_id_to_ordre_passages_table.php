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
            // Un combat Kata (Aka/Ao) donne lieu à deux OrdrePassage (un par
            // athlète) reliés au même combat, pour permettre le vote juge
            // par juge (WKF Kata Competition Rules, Art. 5.5/5.10).
            $table->dropUnique(['config_notation_id', 'inscription_id']);

            $table->foreignUuid('combat_id')
                ->nullable()
                ->after('inscription_id')
                ->constrained('combats')
                ->nullOnDelete();

            // Nom explicite : le nom auto-généré dépasse la limite de 64
            // caractères des identifiants MySQL.
            $table->unique(
                ['config_notation_id', 'inscription_id', 'combat_id'],
                'ordre_passages_config_inscription_combat_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordre_passages', function (Blueprint $table) {
            $table->dropUnique('ordre_passages_config_inscription_combat_unique');
            $table->dropForeign(['combat_id']);
            $table->dropColumn('combat_id');

            $table->unique(['config_notation_id', 'inscription_id']);
        });
    }
};
