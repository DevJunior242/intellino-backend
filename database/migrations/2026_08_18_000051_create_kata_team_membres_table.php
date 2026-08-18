<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kata_team_membres', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kata_team_id')->constrained('kata_teams')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            // 4ème athlète d'une équipe de 4 : remplaçant, ne compte pas
            // parmi les 3 qui performent (Art. 3.5.1).
            $table->boolean('est_reserve')->default(false);
            $table->unique(['kata_team_id', 'student_id']);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('kata_team_membres');
    }
};
