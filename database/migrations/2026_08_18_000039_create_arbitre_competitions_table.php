<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arbitre_competitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('evenement_id')->constrained('evenements')->cascadeOnDelete();
            $table->string('code_acces', 6)
                ->nullable();
            // Est-il connecté sur sa tablette ?
            $table->boolean('connecte')->default(false);
            $table->index(['evenement_id', 'user_id', 'code_acces']);
            $table->timestamps();
            $table->unique(['user_id', 'evenement_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('arbitre_competitions');
    }
};
