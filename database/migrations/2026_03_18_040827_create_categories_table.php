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
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom'); // Senior, Junior, etc.
            //sexe
            $table->enum('sexe', ['M', 'F', 'Mixte'])->default('M');
            $table->integer('age_min')->nullable();
            $table->integer('age_max')->nullable();
            $table->foreignUuid('saison_id')->constrained('saisons')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
