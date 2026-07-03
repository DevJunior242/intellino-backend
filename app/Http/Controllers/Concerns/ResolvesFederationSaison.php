<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Club;
use App\Models\League;
use App\Models\Saison;

/**
 * La saison est toujours définie par la Fédération (voir SaisonController::store).
 * Club et Ligue n'ont pas leur propre saison : on remonte jusqu'à leur
 * fédération de rattachement (Club → Ligue → Fédération).
 */
trait ResolvesFederationSaison
{
    private function saisonActivePour(?string $activeId, ?string $activeType): ?Saison
    {
        if ($activeType === 'Federation') {
            return Saison::where('active', true)
                ->where('organisateur_id', $activeId)
                ->where('organisateur_type', 'Federation')
                ->first();
        }

        if ($activeType === 'Ligue') {
            $league = League::find($activeId);

            if (!$league || !$league->federation_id) {
                return null;
            }

            return Saison::where('active', true)
                ->where('organisateur_id', $league->federation_id)
                ->where('organisateur_type', 'Federation')
                ->first();
        }

        if ($activeType === 'Club') {
            $club = Club::find($activeId);
            $league = $club ? League::find($club->league_id) : null;

            if (!$league || !$league->federation_id) {
                return null;
            }

            return Saison::where('active', true)
                ->where('organisateur_id', $league->federation_id)
                ->where('organisateur_type', 'Federation')
                ->first();
        }

        return null;
    }
}
