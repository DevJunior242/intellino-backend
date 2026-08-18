<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('katas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom');        // Heian Shodan, Bassai Dai...
            $table->string('niveau')      // debutant, intermediate, avance
                ->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('katas');
    }
};
