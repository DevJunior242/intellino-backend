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
        Schema::create('stage_payment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stage_payment_id')
                ->constrained('stage_payments')
                ->cascadeOnDelete();
            $table->foreignUuid('stage_registration_id')
                ->constrained('stage_registrations')
                ->cascadeOnDelete();
            $table->timestamps();

            // Une inscription ne peut appartenir qu'à un seul lot de paiement
            $table->unique('stage_registration_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stage_payment_items');
    }
};
