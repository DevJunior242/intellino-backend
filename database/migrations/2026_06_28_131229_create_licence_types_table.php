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
        Schema::create('licence_types', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Configuration liée à la saison et à la fédé
            $table->foreignUuid('saison_id')->constrained('saisons')->cascadeOnDelete();
            $table->foreignUuid('federation_id')->constrained('federations')->cascadeOnDelete();

            // Infos du type
            $table->string('code', 30); // ex: 'competiteur', 'loisir', 'arbitre', 'coach'
            $table->string('nom', 100); // ex: 'Licence Compétiteur', 'Licence Arbitre'

            // Le Prix initié par la fédération pour cette saison
            $table->decimal('tarif', 10, 2)->default(0.00);

            $table->timestamps();

            // Sécurité : Pas de doublon de code pour une même saison dans une fédé
            $table->unique(['saison_id', 'federation_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licence_types');
    }
};
