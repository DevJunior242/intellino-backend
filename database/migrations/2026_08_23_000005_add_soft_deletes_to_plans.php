<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Suppression douce plutôt que réelle : subscriptions.plan_id a un
// onDelete('cascade') vers plans — un vrai DELETE effacerait l'historique
// d'abonnement de toute organisation ayant souscrit à ce palier. Le
// soft-delete évite complètement ce risque (aucun DELETE SQL n'atteint la
// contrainte), même pattern que PlatformPaymentMethod.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
