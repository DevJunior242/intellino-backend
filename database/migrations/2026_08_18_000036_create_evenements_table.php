<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom'); // ex: "Open de Karaté 2026"
            $table->uuidMorphs('organisateur');
            $table->foreignUuid('saison_id')
                ->nullable()
                ->constrained('saisons')->nullOnDelete();
            $table->string('lieu'); // ex: "Ouagadougou", "Bobo-Dioulasso"
            $table->date('date_debut'); // ex: 2026-05-15
            $table->date('date_fin');
            $table->tinyInteger('status')->default(0); // 0 = En attente, 1 = En cours, 2 = Terminé
            $table->index(['status', 'date_debut', 'date_fin']);
            $table->timestamps();
            $table->index(['organisateur_id', 'organisateur_type', 'saison_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('evenements');
    }
};
