<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_disciplines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('organisateur');
            $table->string('nom'); // kata, kumite
            $table->string('description')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('sub_disciplines');
    }
};
