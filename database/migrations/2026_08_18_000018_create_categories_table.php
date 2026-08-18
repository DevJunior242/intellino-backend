<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom'); // Senior, Junior, etc.
            $table->enum('sexe', ['M', 'F', 'Mixte'])->default('M');
            $table->integer('age_min')->nullable();
            $table->integer('age_max')->nullable();
            $table->decimal('poids_min', 5, 2)->nullable();
            $table->decimal('poids_max', 5, 2)->nullable();
            $table->foreignUuid('saison_id')->constrained('saisons')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['nom', 'sexe', 'saison_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
