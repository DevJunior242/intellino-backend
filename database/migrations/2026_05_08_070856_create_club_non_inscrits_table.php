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
        Schema::create('club_non_inscrits', function (Blueprint $table) {
            $table->uuid('id');
            //name , description,organisateur_id,organisateur_type
            $table->string('name');
            $table->string('description')->nullable();
            $table->uuidMorphs('organisateur');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_non_inscrits');
    }
};
