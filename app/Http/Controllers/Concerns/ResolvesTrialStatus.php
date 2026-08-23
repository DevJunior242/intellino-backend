<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ActivationKey;

/**
 * Un club/une ligue/une fédération créé(e) sans clé d'activation (voir
 * ClubController::store, LeagueController::store, FederationController::store)
 * reste pleinement utilisable pendant trial_activation_days jours. Passé ce
 * délai sans qu'une clé n'ait jamais été consommée pour elle, l'organisation
 * est désactivée (status = 0) par App\Console\Commands\DeactivateExpiredTrials
 * — même mécanisme de blocage que la désactivation manuelle par le super
 * admin, déjà géré par CheckClubRole.
 */
trait ResolvesTrialStatus
{
    private function estActivee($organisation): bool
    {
        return ActivationKey::where('used_by_organisation_id', $organisation->id)
            ->where('is_used', true)
            ->exists();
    }

    private function statutEssaiPour($organisation): array
    {
        if ($this->estActivee($organisation)) {
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
