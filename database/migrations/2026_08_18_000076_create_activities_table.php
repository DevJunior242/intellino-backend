<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('organisateur');

            $table->string('type'); // 'course', 'session', 'exam', 'member'
            $table->string('action'); // 'created', 'updated', 'deleted'
            $table->string('description'); // "A créé le cours Judo Débutant"
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
