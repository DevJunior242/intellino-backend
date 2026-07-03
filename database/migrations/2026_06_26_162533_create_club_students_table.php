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
        Schema::create('club_students', function (Blueprint $table) {
            $table->foreignUuid('club_id')
                ->constrained('clubs')
                ->cascadeOnDelete();

            $table->foreignUuid('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->foreignUuid('saison_id')
                ->constrained('saisons')
                ->cascadeOnDelete();

            // Statut de l'élève dans ce club pour cette saison spécifique
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->primary(['club_id', 'student_id', 'saison_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_students');
    }
};
