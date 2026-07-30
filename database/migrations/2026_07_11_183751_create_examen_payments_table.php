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
        Schema::create('examen_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('examen_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('club_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();

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
        Schema::dropIfExists('examen_payments');
    }
};
