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
        Schema::create('licence_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('saison_id')->constrained('saisons')->cascadeOnDelete();
            $table->foreignUuid('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            // Montant total dû par le club pour TOUTES ses licences de cette saison/fédération
            $table->decimal('amount', 10, 2);
            $table->string('sender_number')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'paid'
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
        Schema::dropIfExists('licence_payments');
    }
};
