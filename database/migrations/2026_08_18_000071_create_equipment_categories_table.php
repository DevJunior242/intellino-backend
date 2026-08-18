<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained()->onDelete('cascade');
            $table->string('name')->unique();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_categories');
    }
};
