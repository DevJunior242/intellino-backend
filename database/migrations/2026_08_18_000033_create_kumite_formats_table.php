<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kumite_formats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();   // poules, eliminatoire, poules_eliminatoire
            $table->string('libelle');          // "Poules uniquement"
            $table->string('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->integer('ordre')->default(0);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('kumite_formats');
    }
};
