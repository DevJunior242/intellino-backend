<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('organisateur');
            $table->foreignUuid('current_grade_id')->constrained('grades')->onDelete('cascade');
            $table->foreignUuid('next_grade_id')->constrained('grades')->onDelete('cascade');

            $table->tinyInteger('status')->default(0);
            // Prix d'inscription par candidat. 0 = examen gratuit.
            $table->decimal('price', 10, 2)->default(0.00);

            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->date('start_date');
            $table->date('old_start_date')->nullable();
            $table->date('end_date');
            $table->date('old_end_date')->nullable();
            $table->time('start_time');
            $table->time('replacement_start_time')->nullable();
            $table->foreignUuid('replacement_instructor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->time('end_time');
            $table->time('replacement_end_time')->nullable();
            $table->time('actual_start_time')->nullable();
            $table->time('actual_end_time')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('saison_id')->nullable()->constrained('saisons')->nullOnDelete();

            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('examens');
    }
};
