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

            // Les liaisons clés
            $table->foreignUuid('saison_id')->constrained('saisons')->cascadeOnDelete();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('federation_id')->constrained('federations')->cascadeOnDelete();

            $table->foreignUuid('licence_type_id')->nullable()->constrained('licence_types')->nullOnDelete();

            // Snapshot du moment (Historique)
            $table->string('grade_au_moment', 50)->nullable();
            $table->decimal('montant_paye', 10, 2)->nullable(); // Copie du tarif au moment du paiement (reçu fiscal)

            // Identification et Statut
            $table->string('numero')->nullable()->unique(); // Matricule de l'élève
            $table->tinyInteger('status')->default(0); // 0 = En attente, 1 = Payé, 2 = Validé

            $table->timestamps();
            $table->softDeletes();

            // Un élève ne peut avoir qu'un seul type de licence spécifique par saison
            $table->unique(['student_id', 'saison_id'], 'student_saison_unique');
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
