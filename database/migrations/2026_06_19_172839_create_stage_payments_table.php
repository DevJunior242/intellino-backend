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
        Schema::create('stage_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stage_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('club_id')->constrained()->onDelete('cascade');

            // Le montant total passe aussi en decimal
            $table->decimal('amount', 10, 2);

            $table->string('sender_number')->nullable();
            $table->string('status')->default('pending');
            $table->string('transaction_id')->nullable();
            $table->timestamp('declared_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stage_payments');
    }
};
