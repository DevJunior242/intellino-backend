<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ActivationKey;
use App\Models\Subscription;

/**
 * Un club/une ligue/une fédération créé(e) sans clé d'activation (voir
 * ClubController::store, LeagueController::store, FederationController::store)
 * reste pleinement utilisable pendant trial_activation_days jours. Passé ce
 * délai, l'organisation est désactivée (status = 0) par
 * App\Console\Commands\DeactivateExpiredTrials — SAUF si elle a soit une clé
 * d'activation consommée, soit un abonnement Intellino payé et toujours dans
 * sa période (voir Subscription) : les deux chemins sont volontairement
 * indépendants (clé = accès accordé manuellement par le super admin, tarif =
 * libre-service) et ont le même effet, plutôt que d'exiger l'un pour
 * débloquer l'autre.
 */
trait ResolvesTrialStatus
{
    /**
     * "Déjà consommé une clé" au sens strict — utilisé par
     * OrganisationController::activate() pour empêcher de re-saisir une clé,
     * indépendamment du fait qu'un abonnement payé existe ou non (on ne veut
     * pas bloquer la saisie d'une clé juste parce qu'un abonnement tourne).
     */
    private function aUneCleActivee($organisation): bool
    {
        return ActivationKey::where('used_by_organisation_id', $organisation->id)
            ->where('is_used', true)
            ->exists();
    }

    private function aUnAbonnementPayeEnCours($organisation, string $organisateurType): bool
    {
        return Subscription::where('organisateur_id', $organisation->id)
            ->where('organisateur_type', $organisateurType)
            ->where('status', Subscription::STATUS_PAID)
            ->where('end_date', '>=', now()->toDateString())
            ->exists();
    }

    private function estActivee($organisation, string $organisateurType): bool
    {
        return $this->aUneCleActivee($organisation)
            || $this->aUnAbonnementPayeEnCours($organisation, $organisateurType);
    }

    private function statutEssaiPour($organisation, string $organisateurType): array
    {
        if ($this->estActivee($organisation, $organisateurType)) {
            return ['activated' => true, 'days_remaining' => null, 'trial_expires_at' => null];
        }

        $trialDays = (int) config('app.trial_activation_days');
        $expiresAt = $organisation->created_at->copy()->startOfDay()->addDays($trialDays);
        $daysRemaining = max(0, now()->startOfDay()->diffInDays($expiresAt, false));

        return [
            'activated' => false,
            'days_remaining' => (int) $daysRemaining,
            'trial_expires_at' => $expiresAt->toDateString(),
        ];
    }
}
