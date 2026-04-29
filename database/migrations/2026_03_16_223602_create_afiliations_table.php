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
        Schema::create('affiliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('saison', 20);              // "2024-2025"
            $table->tinyInteger('status')->default(1);
            $table->decimal('cotisation', 10, 2)->nullable();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->timestamps();
            $table->unique(['club_id', 'saison']);    // 1 affiliation par saison
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliations');
    }
};
