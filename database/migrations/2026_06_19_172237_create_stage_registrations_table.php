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
        Schema::create('stage_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stage_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade'); // Le pratiquant inscrit
            $table->foreignUuid('club_id')->constrained(); // Le club qui paye/inscrit
            $table->boolean('is_present')->default(false); // Pour l'émargement le jour J
            $table->string('payment_status')->default('pending'); // 'pending', 'paid'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stage_registrations');
    }
};
