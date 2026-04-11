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
        Schema::create('plateaux', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('evenement_id')->constrained('evenements')->cascadeOnDelete();
            $table->string('nom');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plateaux');
    }
};
