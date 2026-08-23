<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// subscriptions/subscription_payments existaient déjà mais n'ont jamais été
// branchés à un vrai flux de paiement (aucune ligne réelle, redirection vers
// un config('services.stripe.payment_url') jamais configuré) — sans risque
// de migration destructive, contrairement au reste de l'app qui a de vraies
// données depuis le dernier migrate:fresh.
return new class extends Migration
{
    public function up(): void
    {
        // Moyens de paiement d'Intellino elle-même (comptes recevant les
        // abonnements) — distinct de payment_methods, qui sert aux
        // Club/Ligue/Fédération à se faire payer entre eux, pas la
        // plateforme elle-même (qui n'est pas une "organisation").
        Schema::create('platform_payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider');
            $table->string('label');
            $table->string('account_number');
            $table->string('account_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Un abonnement peut être souscrit par un Club, une Ligue ou une
        // Fédération — pas seulement un Club, cohérent avec le reste de la
        // tarification (voir plans.organisateur_type).
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->dropColumn('club_id');
            $table->uuidMorphs('organisateur');
            // Prix figé au moment de la souscription (indépendant d'un
            // changement ultérieur du prix du palier par le super admin).
            $table->decimal('amount', 10, 2)->default(0)->after('plan_id');
        });

        // Le flux déclarer/confirmer (même logique que Transaction) : un
        // paiement est déclaré par l'organisation puis confirmé par le
        // super admin, plutôt que le simple relevé "payment_method +
        // transaction_id + paid_at" d'origine qui ne portait aucun statut.
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'paid_at']);
            $table->foreignUuid('platform_payment_method_id')
                ->nullable()
                ->after('subscription_id')
                ->constrained('platform_payment_methods')
                ->nullOnDelete();
            $table->string('sender_number')->nullable()->after('transaction_id');
            $table->string('status')->default('declared')->after('amount'); // declared | confirmed | rejected
            $table->timestamp('declared_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignUuid('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropForeign(['platform_payment_method_id']);
            $table->dropForeign(['confirmed_by_user_id']);
            $table->dropColumn([
                'platform_payment_method_id',
                'sender_number',
                'status',
                'declared_at',
                'confirmed_at',
                'confirmed_by_user_id',
            ]);
            $table->string('payment_method')->default('');
            $table->date('paid_at')->nullable();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('amount');
            $table->dropMorphs('organisateur');
            $table->foreignUuid('club_id')->nullable()->constrained('clubs')->cascadeOnDelete();
        });

        Schema::dropIfExists('platform_payment_methods');
    }
};
