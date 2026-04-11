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
        Schema::create('poule_inscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('poule_id')
                ->constrained('poules')
                ->cascadeOnDelete();

            $table->foreignUuid('inscription_id')
                ->constrained('inscriptions')
                ->cascadeOnDelete();

            $table->integer('points_victoire')->default(0); // Ex: 3 pts par victoire, 1 pt nul
            $table->integer('total_points_marques')->default(0);
            $table->integer('total_points_encaisses')->default(0);

            $table->integer('rang')->nullable();

            $table->unique(['poule_id', 'inscription_id']);
            //primary 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poule_inscriptions');
    }
};
