<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('examen_payment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('examen_payment_id')
                ->constrained('examen_payments')
                ->cascadeOnDelete();
            $table->foreignUuid('examen_candidat_id')
                ->constrained('examen_candidats')
                ->cascadeOnDelete();
            $table->timestamps();

            // Un candidat ne peut appartenir qu'à un seul lot de paiement
            $table->unique('examen_candidat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('examen_payment_items');
    }
};
