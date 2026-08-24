<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fiche santé côté Club, remplie dès l'inscription (voir StudentController::store)
// et modifiable ensuite (renouvellement du certificat médical) — purement
// informative pour l'instant, aucune action n'est bloquée par son absence.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_health_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->unique()->constrained('students')->cascadeOnDelete();

            $table->string('groupe_sanguin')->nullable();
            $table->text('allergies')->nullable();
            $table->text('conditions_medicales')->nullable();

            $table->string('medecin_nom')->nullable();
            $table->string('medecin_telephone')->nullable();

            $table->string('contact_urgence_nom')->nullable();
            $table->string('contact_urgence_telephone')->nullable();
            $table->string('contact_urgence_relation')->nullable();

            $table->boolean('certificat_medical_fourni')->default(false);
            $table->date('certificat_medical_expire_le')->nullable();

            $table->text('notes')->nullable();

            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_health_profiles');
    }
};
