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
        Schema::create('judge_votes', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('combat_id')->constrained('combats')->cascadeOnDelete();

            // Identifiant du juge (ex: 'juge_1', 'juge_2', etc.)
            $table->tinyInteger('juge_numero');
            // 'aka' ou 'ao'
            $table->string('combattant');

            // 'yuko', 'waza-ari', 'ippon', 'penalite'
            $table->string('type');

            $table->timestamp('clicked_at', 3);

            $table->timestamps();

            $table->index(['combat_id', 'combattant', 'type', 'clicked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('judge_votes');
    }
};
