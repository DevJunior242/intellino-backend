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
        Schema::create('combat_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('combat_id')->constrained('combats')->cascadeOnDelete();
            $table->foreignUuid('rotation_arbitre_id')->constrained('rotation_arbitres')->cascadeOnDelete();
            $table->string('type');
            $table->string('combattant');
            $table->tinyInteger('valeur')->default(0); // pts générés
            $table->timestamp('signale_a');
            $table->integer('temps_match');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('combat_actions');
    }
};
