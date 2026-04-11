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
        Schema::create('student_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs');
            $table->foreignUuid('student_id')->constrained('students');
            $table->foreignUuid('pricing_plan_id')->constrained();
            // Finances
            $table->decimal('total_amount', 10, 2);
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('balance', 10, 2)->default(0);
            // État et Méthode
            $table->string('status')->default('paid'); // paid, partial, debt_repayment
            $table->string('payment_method')->default('cash');
            $table->string('payment_reference')->nullable();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->foreignUuid('parent_id')->nullable()->constrained('student_payments')->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_payments');
    }
};
