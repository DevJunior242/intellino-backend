<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * Nombre d'utilisateurs actifs d'une organisation (élèves actifs pour un
 * Club, agrégés pour une Ligue/Fédération sur tous les clubs affiliés) et
 * position par rapport aux paliers Intellino (voir Plan::pourEffectif) —
 * sert à avertir avant la limite plutôt qu'à bloquer brutalement : bloquer
 * l'inscription d'un élève à cause d'un plafond SaaS interne pénaliserait
 * l'activité réelle du club, donc on prévient et on laisse l'admin choisir
 * de changer de palier lui-même (jamais de bascule automatique de
 * facturation).
 */
trait ResolvesEffectifOrganisation
{
    private function nombreUtilisateursActifs(string $activeId, string $activeType): int
    {
        $query = DB::table('club_students')
            ->join('clubs', 'clubs.id', '=', 'club_students.club_id')
            ->where('club_students.is_active', true)
            ->whereNull('club_students.deleted_at');

        if ($activeType === 'Club') {
            $query->where('club_students.club_id', $activeId);
        } elseif ($activeType === 'Ligue') {
            $query->where('clubs.league_id', $activeId);
        } elseif ($activeType === 'Federation') {
            $query->join('leagues', 'leagues.id', '=', 'clubs.league_id')
                ->where('leagues.federation_id', $activeId);
        } else {
            return 0;
        }

        return (int) $query->distinct('club_students.student_id')->count('club_students.student_id');
    }

    private function statutPalierPour(string $activeType, int $nbUsers): array
    {
        $palier = Plan::pourEffectif($activeType, $nbUsers);

        $base = [
            'nombre_utilisateurs' => $nbUsers,
            'palier' => $palier,
            'palier_suivant' => null,
            'pourcentage' => null,
            'niveau_alerte' => null, // null | 'proche' | 'depasse'
            'message' => null,
        ];

        if (!$palier || $palier->max_users === null) {
            return $base;
        }

        $pourcentage = $palier->max_users > 0
            ? (int) round(($nbUsers / $palier->max_users) * 100)
            : 0;

        $palierSuivant = Plan::where('organisateur_type', $activeType)
            ->where('min_users', '>', $palier->max_users)
            ->orderBy('min_users')
            ->first();

        $niveauAlerte = null;
        $message = null;

        if ($nbUsers > $palier->max_users) {
            $niveauAlerte = 'depasse';
            $message = $palierSuivant
                ? "Vous avez dépassé la limite de votre palier « {$palier->name} » ({$nbUsers}/{$palier->max_users} utilisateurs). Passez à « {$palierSuivant->name} » pour continuer sereinement."
                : "Vous avez dépassé la limite de votre palier « {$palier->name} » ({$nbUsers}/{$palier->max_users} utilisateurs). Contactez-nous pour une offre sur-mesure.";
        } elseif ($pourcentage >= 90) {
            $niveauAlerte = 'proche';
            $restants = $palier->max_users - $nbUsers;
            $suffixe = $restants > 1 ? 's' : '';
            $message = $palierSuivant
                ? "Il vous reste {$restants} utilisateur{$suffixe} avant la limite de votre palier « {$palier->name} ». Pensez à passer à « {$palierSuivant->name} » si vous continuez à grandir."
                : "Il vous reste {$restants} utilisateur{$suffixe} avant la limite de votre palier « {$palier->name} ».";
        }

        return [
            'nombre_utilisateurs' => $nbUsers,
            'palier' => $palier,
            'palier_suivant' => $palierSuivant,
            'pourcentage' => $pourcentage,
            'niveau_alerte' => $niveauAlerte,
            'message' => $message,
        ];
    }
}
