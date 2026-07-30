<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Club;
use App\Models\League;
use App\Models\Saison;

/**
 * Résout la saison active applicable à un organisateur (Club, Ligue ou Fédération).
 *
 * Une Fédération a toujours sa propre saison. Un Club/une Ligue affilié(e)
 * hérite de la saison de son parent (Club → Ligue → Fédération). Mais un
 * Club ou une Ligue INDÉPENDANT(E) (pas de league_id / pas de federation_id)
 * peut posséder et gérer sa propre saison — voir SaisonController::store().
 * Règle : utilise ta propre saison si tu en as une, sinon remonte au parent.
 */
trait ResolvesActiveSaison
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

            if (!$league) {
                return null;
            }

            // Ligue indépendante : elle gère sa propre saison
            if (!$league->federation_id) {
                return Saison::where('active', true)
                    ->where('organisateur_id', $league->id)
                    ->where('organisateur_type', 'Ligue')
                    ->first();
            }

            return Saison::where('active', true)
                ->where('organisateur_id', $league->federation_id)
                ->where('organisateur_type', 'Federation')
                ->first();
        }

        if ($activeType === 'Club') {
            $club = Club::find($activeId);

            if (!$club) {
                return null;
            }

            // Club indépendant (aucune ligue) : il gère sa propre saison
            if (!$club->league_id) {
                return Saison::where('active', true)
                    ->where('organisateur_id', $club->id)
                    ->where('organisateur_type', 'Club')
                    ->first();
            }

            $league = League::find($club->league_id);

            if (!$league) {
                return null;
            }

            // Ligue de rattachement indépendante : hérite de sa saison à elle
            if (!$league->federation_id) {
                return Saison::where('active', true)
                    ->where('organisateur_id', $league->id)
                    ->where('organisateur_type', 'Ligue')
                    ->first();
            }

            return Saison::where('active', true)
                ->where('organisateur_id', $league->federation_id)
                ->where('organisateur_type', 'Federation')
                ->first();
        }

        return null;
    }
}
