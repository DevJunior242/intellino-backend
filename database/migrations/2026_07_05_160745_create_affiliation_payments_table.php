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
        Schema::create('affiliation_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliation_id')->constrained('affiliations')->cascadeOnDelete();
            $table->foreignUuid('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('sender_number')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'declared', 'paid'
            $table->string('transaction_id')->nullable();
            $table->timestamp('declared_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['affiliation_id', 'club_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliation_payments');
    }
};
