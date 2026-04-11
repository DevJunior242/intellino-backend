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
        Schema::create('examen_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('examen_id')->constrained('examens')->onDelete('cascade');
            $table->foreignUuid('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignUuid('enchainement_id')->constrained('grade_enchainements')->onDelete('cascade');
            $table->decimal('score', 8, 2)->nullable();
            $table->text('comment')->nullable();
            $table->foreignUuid('evaluated_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['examen_id', 'student_id', 'enchainement_id', 'evaluated_by'], 'eval_unique_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('examen_evaluations');
    }
};
