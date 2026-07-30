<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Club;
use App\Models\League;
use App\Models\Saison;
use App\Models\Federation;

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
    /**
     * Organisation (Club, Ligue ou Fédération) dont il faut vérifier/gérer
     * la saison active pour ce contexte : remonte récursivement la chaîne
     * Club → Ligue → Fédération jusqu'à la première entité indépendante,
     * ou jusqu'à la Fédération (qui gère toujours la sienne).
     */
    private function organisateurEffectifSaison(?string $activeId, ?string $activeType): ?array
    {
        if ($activeType === 'Federation') {
            $federation = Federation::find($activeId);

            return $federation
                ? ['type' => 'Federation', 'id' => $federation->id, 'nom' => $federation->name]
                : null;
        }

        if ($activeType === 'Ligue') {
            $league = League::find($activeId);

            if (!$league) {
                return null;
            }

            // Ligue indépendante : elle gère sa propre saison
            if (!$league->federation_id) {
                return ['type' => 'Ligue', 'id' => $league->id, 'nom' => $league->name];
            }

            return $this->organisateurEffectifSaison($league->federation_id, 'Federation');
        }

        if ($activeType === 'Club') {
            $club = Club::find($activeId);

            if (!$club) {
                return null;
            }

            // Club indépendant (aucune ligue) : il gère sa propre saison
            if (!$club->league_id) {
                return ['type' => 'Club', 'id' => $club->id, 'nom' => $club->name];
            }

            return $this->organisateurEffectifSaison($club->league_id, 'Ligue');
        }

        return null;
    }

    private function saisonActivePour(?string $activeId, ?string $activeType): ?Saison
    {
        $organisateur = $this->organisateurEffectifSaison($activeId, $activeType);

        if (!$organisateur) {
            return null;
        }

        return Saison::where('active', true)
            ->where('organisateur_id', $organisateur['id'])
            ->where('organisateur_type', $organisateur['type'])
            ->first();
    }

    /**
     * Message à afficher quand saisonActivePour() ne trouve rien : précise
     * QUI (quelle ligue/fédération) doit définir la saison quand ce n'est
     * pas l'organisation courante, plutôt qu'un message générique qui
     * laisserait croire à l'utilisateur que c'est à lui de le faire alors
     * qu'il est rattaché à un parent qui gère sa propre saison.
     */
    private function messageAucuneSaisonActivePour(?string $activeId, ?string $activeType): string
    {
        $générique = "Aucune saison sportive active n'a été configurée.";

        $organisateur = $this->organisateurEffectifSaison($activeId, $activeType);

        if (!$organisateur || $organisateur['type'] === $activeType) {
            return $générique;
        }

        $niveau = $organisateur['type'] === 'Ligue' ? 'ligue' : 'fédération';
        $rattachement = $activeType === 'Club' ? 'club' : 'ligue';
        $nom = $organisateur['nom'] ? " ({$organisateur['nom']})" : '';

        return "{$générique} Votre {$rattachement} est rattaché(e) à une {$niveau}{$nom} : c'est à l'administrateur de cette {$niveau} de définir sa saison active avant que vous puissiez continuer.";
    }
}
