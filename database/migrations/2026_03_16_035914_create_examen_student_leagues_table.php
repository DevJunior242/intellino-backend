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
        Schema::create('examen_student_leagues', function (Blueprint $table) {
            $table->foreignUuid('examen_league_id')->constrained('examen_leagues')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('average', 5, 2)->nullable();
            $table->boolean('passed')->default(false);
            $table->primary(['examen_league_id', 'student_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('examen_student_leagues');
    }
};
