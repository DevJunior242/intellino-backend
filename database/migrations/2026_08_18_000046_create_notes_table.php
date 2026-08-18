<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ordre_passage_id')->constrained('ordre_passages')->cascadeOnDelete();
            $table->foreignUuid('rotation_arbitre_id')->constrained('rotation_arbitres')->cascadeOnDelete();
            $table->decimal('valeur', 5, 2);
            $table->dateTime('note_a');
            $table->timestamps();

            $table->unique(['ordre_passage_id', 'rotation_arbitre_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
