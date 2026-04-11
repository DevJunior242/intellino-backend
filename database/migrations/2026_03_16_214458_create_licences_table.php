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
        Schema::create('licences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();

            // Grade au moment de l'émission (snapshot)
            $table->string('grade_au_moment', 50)->nullable(); // ex: "Ceinture verte", "2e Dan"

            // Type de licence
            $table->string('type', 30)->default('competiteur');
            // competiteur, loisir, dirigeant, arbitre, entraineur...

            $table->string('saison', 9);
            $table->string('numero')->unique();
            $table->decimal('montant', 10, 2)->nullable(); // prix payé
            $table->string('statut')->default('active');
            $table->date('date_emission');
            $table->date('date_expiration');
            $table->timestamps();

            $table->unique(['student_id', 'saison']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licences');
    }
};
