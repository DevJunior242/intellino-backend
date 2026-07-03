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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Génère automatiquement : 'organisateur_type' (string) et 'organisateur_id' (uuid)
            $table->uuidMorphs('organisateur');

            // Détails du moyen de paiement
            $table->string('provider', 30); // ex: 'orange_money', 'moov_money', 'wave', 'virement_bancaire'
            $table->string('label', 100);   // ex: 'Compte Principal Orange Money FBK'

            // Les coordonnées pour le transfert
            $table->string('account_number', 50); // Le numéro de téléphone ou le RIB qui reçoit l'argent
            $table->string('account_name', 100)->nullable(); // Le nom officiel du compte (ex: "Fédération Burkinabè de Karaté")

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
