<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examen_candidats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('examen_id')->constrained('examens')->onDelete('cascade');
            $table->foreignUuid('student_id')->constrained('students')->onDelete('cascade');
            // Club qui inscrit/paie ce candidat (utilisé pour scoper les lots de paiement).
            $table->foreignUuid('club_id')->nullable()->constrained('clubs')->nullOnDelete();
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
            $table->unique(['examen_id', 'student_id'], 'examen_candidat_unique_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('examen_candidats');
    }
};
