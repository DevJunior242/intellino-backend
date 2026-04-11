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
        Schema::create('rotation_arbitres', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('arbitre_competition_id')
                ->constrained('arbitre_competitions')
                ->cascadeOnDelete();
            $table->foreignUuid('config_notation_id')
                ->constrained('config_notations')
                ->cascadeOnDelete();
            $table->boolean('est_superviseur')->default(false);
            $table->integer('ordre');           // position dans la file : 1, 2...20
            $table->boolean('actif')->default(false);  // sur un poste ou au banc
            $table->integer('poste')->nullable();      // 1..7 si actif, null si banc
            $table->integer('nb_passages')->default(0); // nb athlètes jugés
            $table->unique(['config_notation_id', 'arbitre_competition_id'], 'unique_arbitre_per_config');

            $table->unique(['config_notation_id', 'poste']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rotation_arbitres');
    }
};
