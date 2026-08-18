<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Clés étrangères liées
            $table->foreignUuid('federation_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('role_id')->constrained()->onDelete('cascade'); // admin_federation, dtn, etc.

            // Gestion du mandat
            $table->date('mandate_start_at')->nullable();
            $table->date('mandate_end_at')->nullable();
            $table->boolean('mandate_status')->default(true); // true = 1 (actif), false = 0 (expiré/révoqué)

            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('federation_users');
    }
};
