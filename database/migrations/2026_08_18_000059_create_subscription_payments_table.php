<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->onDelete('cascade');
            $table->string('payment_method');
            $table->string('transaction_id');
            $table->decimal('amount', 10, 2);
            $table->date('paid_at');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
