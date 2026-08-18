<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->onDelete('cascade');
            $table->foreignUuid('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignUuid('session_id')->constrained('session_models')->onDelete('cascade');
            $table->enum('status', ['present', 'absent'])->default('absent');
            $table->foreignUuid('saison_id')->nullable()->constrained('saisons')->nullOnDelete();
            $table->timestamps();
            $table->unique(['club_id', 'student_id', 'session_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
