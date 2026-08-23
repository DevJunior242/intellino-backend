<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// transaction_id est resté NOT NULL depuis la création initiale de la table
// (avant le passage au flux déclarer/confirmer) alors que le formulaire de
// déclaration l'affiche comme "optionnel" et le controller l'accepte comme
// nullable — toute déclaration sans référence de transaction plantait donc
// en base (SQLSTATE 23000). Pas de doctrine/dbal installé, d'où le SQL brut
// plutôt que ->change().
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE subscription_payments MODIFY transaction_id VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE subscription_payments MODIFY transaction_id VARCHAR(255) NOT NULL DEFAULT ''");
    }
};
