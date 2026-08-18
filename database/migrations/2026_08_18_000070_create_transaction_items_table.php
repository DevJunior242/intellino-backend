<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->constrained('transactions')->cascadeOnDelete();
            // 'licence' | 'stage_registration' | 'examen_candidat' — chaînes
            // fixes résolues manuellement (pas de morphTo Eloquent : cette
            // appli n'utilise pas de morph map global).
            $table->string('itemable_type');
            $table->uuid('itemable_id');
            $table->timestamps();

            // Un même licence/inscription-stage/candidat-examen ne peut
            // appartenir qu'à un seul lot de paiement.
            $table->unique(['itemable_type', 'itemable_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
