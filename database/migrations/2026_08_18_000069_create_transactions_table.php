<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Unifie 4 anciens systèmes de paiement (licence_payments,
        // affiliation_payments, stage_payments, examen_payments) — même
        // machine à états (pending -> declared -> paid) pour tout ce qui
        // fait entrer de l'argent d'un club vers une Ligue/Fédération.
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete(); // côté payeur, toujours un club
            $table->uuidMorphs('organisateur'); // côté receveur : Club/Ligue/Federation
            $table->foreignUuid('saison_id')->nullable()->constrained('saisons')->nullOnDelete();

            $table->string('payable_type'); // 'licence_lot' | 'affiliation' | 'stage' | 'examen'
            $table->uuid('payable_id')->nullable(); // affiliation_id / stage_id / examen_id ; null pour licence_lot

            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending'); // pending | declared | paid
            $table->string('sender_number')->nullable();
            $table->string('transaction_id')->nullable();
            $table->timestamp('declared_at')->nullable();
            $table->foreignUuid('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organisateur_type', 'organisateur_id', 'status']);
            $table->index(['payable_type', 'payable_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
