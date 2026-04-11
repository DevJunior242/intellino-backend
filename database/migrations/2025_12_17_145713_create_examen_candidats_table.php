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
        Schema::create('examen_candidats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('examen_id')->constrained('examens')->onDelete('cascade');
            $table->foreignUuid('student_id')->constrained('students')->onDelete('cascade');
            $table->enum('status', ['registered', 'absent', 'evaluated'])
            ->nullable()
            ->default('registered');
            $table->timestamps();
            $table->unique(['examen_id', 'student_id'],'examen_candidat_unique_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('examen_candidats');
    }
};
