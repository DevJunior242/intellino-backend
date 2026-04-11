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
        Schema::create('ordre_passages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('config_notation_id')->constrained('config_notations')->cascadeOnDelete();
            $table->foreignUuid('inscription_id')->constrained('inscriptions'); // ou inscription_id
            $table->integer('ordre'); // 1, 2, 3... pour la file d'attente
            $table->string('statut')->default('en_attente'); // en_attente, en_cours, termine, blesse
            $table->float('score_final')->nullable();
            $table->timestamps();

            $table->unique(['config_notation_id', 'inscription_id']);
            $table->unique(['config_notation_id', 'ordre']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordre_passages');
    }
};
