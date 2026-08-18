<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveaux_competitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom'); // ex: "Ligue du Centre", "Championnat National", "Coupe de l'Ambassadeur"
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('niveaux_competitions');
    }
};
