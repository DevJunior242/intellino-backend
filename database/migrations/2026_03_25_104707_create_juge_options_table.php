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
        Schema::create('juge_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('valeur')->unique(); // 5, 7 (demain peut-être 3 ou 9)
            $table->string('libelle');           // "5 juges", "7 juges"
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('juge_options');
    }
};
