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
        Schema::create('session_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('parent_session_id')->nullable()->constrained('session_models')->nullOnDelete();
            $table->foreignUuid('replacement_instructor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // 0 - Not started, 1 - In progress, 2 - Finished

            $table->tinyInteger('status')->default(0);
            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->date('session_date');
            $table->date('old_session_date')->nullable();
            $table->time('start_time');
            $table->time('replacement_start_time')->nullable();
            $table->time('end_time');
            $table->time('replacement_end_time')->nullable();
            $table->time('actual_start_time')->nullable();
            $table->time('actual_end_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_models');
    }
};
