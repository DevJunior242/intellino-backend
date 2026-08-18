<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('saison_id')->constrained('saisons')->cascadeOnDelete();
            $table->string('title');
            $table->string('type'); // 'arbitrage', 'technique', 'combat'
            $table->decimal('price', 10, 2)->default(0.00);
            $table->uuidMorphs('organisateur');
            $table->string('level'); // 'club', 'league', 'federal'
            $table->tinyInteger('status')->default(1); // 1 : active, 0 : inactive
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
