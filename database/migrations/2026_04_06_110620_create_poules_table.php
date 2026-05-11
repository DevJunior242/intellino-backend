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
        Schema::create('poules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_notation_id')->constrained('config_notations')->cascadeOnDelete();

            $table->string('nom'); // "Poule A", "Quart de finale 1"

            // On garde 'etape' pour savoir si c'est un tour de qualif ou une phase finale
            $table->string('etape')->default('qualification');
            // 0 = Création, 1 = Matchs lancés, 2 = Résultats validés (Fermée)
            $table->tinyInteger('status')->default(0);
            $table->integer('ordre')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poules');
    }
};
