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
        Schema::create('grade_enchainements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('examen_id')->constrained('examens')->onDelete('cascade');
            $table->foreignUuid('current_grade_id')->constrained('grades')->onDelete('cascade');
            $table->string('name');
            $table->integer('diviseur');
            $table->text('description')->nullable();
            $table->integer('order');
            $table->timestamps();
            $table->unique(['examen_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_enchainements');
    }
};
