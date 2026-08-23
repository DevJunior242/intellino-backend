<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// subscriptions.status est un ENUM figé à sa création (pending_payment |
// paid | expired), jamais élargi depuis. Un changement de palier doit
// pouvoir marquer l'ancien abonnement comme "cancelled" (remplacé) — sans
// cet ajout, MySQL rejette silencieusement toute valeur hors de l'ENUM et
// stocke une chaîne vide à la place (aucune erreur levée), ce qui cassait le
// flux de changement de palier sans avertissement.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE subscriptions MODIFY status ENUM('pending_payment', 'paid', 'expired', 'cancelled') NOT NULL DEFAULT 'pending_payment'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE subscriptions MODIFY status ENUM('pending_payment', 'paid', 'expired') NOT NULL DEFAULT 'pending_payment'"
        );
    }
};
