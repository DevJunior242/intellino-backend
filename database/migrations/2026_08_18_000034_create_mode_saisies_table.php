<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mode_saisies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();   // centralise, tablettes
            $table->string('libelle');          // "Saisie centralisée"
            $table->string('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->integer('ordre')->default(0);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('mode_saisies');
    }
};
