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
        Schema::table('combats', function (Blueprint $table) {
            // Score Kata retenu par camp (somme après retrait des extrêmes,
            // cumulé Kata+Bunkai pour une finale d'équipe) — distinct des
            // colonnes entières score_final_aka/ao utilisées par le Kumite.
            $table->decimal('score_kata_aka', 6, 2)->nullable()->after('score_final_ao');
            $table->decimal('score_kata_ao', 6, 2)->nullable()->after('score_kata_aka');

            // Nombre de votes de juges obtenus par camp (majorité — Art. 5.4.2/5.5.1).
            $table->unsignedTinyInteger('votes_aka')->nullable()->after('score_kata_ao');
            $table->unsignedTinyInteger('votes_ao')->nullable()->after('votes_aka');

            // Repère du chrono Kata+Bunkai (indicatif seulement, 5 min max —
            // Art. 3.5.6), posé au début de la phase Kata d'une finale d'équipe.
            $table->timestamp('debut_prestation_at')->nullable()->after('votes_ao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('combats', function (Blueprint $table) {
            $table->dropColumn([
                'score_kata_aka',
                'score_kata_ao',
                'votes_aka',
                'votes_ao',
                'debut_prestation_at',
            ]);
        });
    }
};
