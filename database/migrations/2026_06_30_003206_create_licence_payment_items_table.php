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
        Schema::create('licence_payment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('licence_payment_id')
                ->constrained('licence_payments')
                ->cascadeOnDelete();
            $table->foreignUuid('licence_id')
                ->constrained('licences')
                ->cascadeOnDelete();
            $table->timestamps();

            // Une licence ne peut appartenir qu'à un seul lot de paiement
            $table->unique('licence_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licence_payment_items');
    }
};
