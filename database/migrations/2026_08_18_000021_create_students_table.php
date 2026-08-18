<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users');
            // Athlète indépendant (sans club) inscrit directement par une
            // Ligue/Fédération — null si rattaché à un club (déductible via
            // club_students -> clubs -> league_id -> federation_id).
            $table->uuid('organisateur_id')->nullable();
            $table->string('organisateur_type')->nullable();
            $table->boolean('is_adult')->default(false);
            $table->string('fullname');
            $table->date('birthdate');
            $table->string('matricule')->nullable();
            $table->enum('sex', ['M', 'F']);
            $table->string('photo')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['organisateur_type', 'organisateur_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
