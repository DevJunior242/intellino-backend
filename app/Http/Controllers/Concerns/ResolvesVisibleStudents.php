<?php

namespace App\Http\Controllers\Concerns;

use App\Models\League;
use App\Models\Student;

/**
 * Élèves visibles par l'organisateur connecté : un Club ne voit que le
 * sien, une Ligue/Fédération voit tous les clubs de son ressort
 * (hiérarchie club -> ligue -> fédération) PLUS les athlètes indépendants
 * (sans club) qu'elle a elle-même inscrits — même logique que
 * InscriptionController::studentsDisponibles() pour les épreuves.
 * Partagé entre StudentController (recherche générale) et CandidatController
 * (candidats éligibles à un examen) pour ne pas dupliquer cette hiérarchie.
 */
trait ResolvesVisibleStudents
{
    /**
     * $clubId (optionnel) restreint encore à un club précis dans ce ressort
     * (et exclut alors les indépendants, non rattachés à ce club précis).
     */
    private function studentsVisiblesPar(string $activeId, string $activeType, ?string $clubId = null, ?string $q = null)
    {
        $appliquerRecherche = fn ($query) => $q
            ? $query->where('students.fullname', 'LIKE', '%' . $q . '%')
            : $query;

        $viaClub = Student::query()
            ->join('club_students', 'club_students.student_id', '=', 'students.id')
            ->whereNull('club_students.deleted_at')
            ->select('students.*');

        if ($activeType === 'Club') {
            $viaClub->where('club_students.club_id', $activeId);
        } elseif ($activeType === 'Ligue') {
            $viaClub->join('clubs', 'clubs.id', '=', 'club_students.club_id')
                ->where('clubs.league_id', $activeId);
        } elseif ($activeType === 'Federation') {
            $liguesIds = League::where('federation_id', $activeId)->pluck('id');
            $viaClub->join('clubs', 'clubs.id', '=', 'club_students.club_id')
                ->whereIn('clubs.league_id', $liguesIds);
        }

        if ($clubId && $activeType !== 'Club') {
            $viaClub->where('club_students.club_id', $clubId);
        }

        $students = $appliquerRecherche($viaClub)->distinct()->get();

        if ($activeType !== 'Club' && !$clubId) {
            $independants = $this->independantsVisiblesPar($activeId, $activeType, $q);
            $students = $students->concat($independants);
        }

        return $students->unique('id')->sortBy('fullname')->values();
    }

    /**
     * Athlètes indépendants (sans club) inscrits directement par cette
     * Ligue/Fédération (ou l'une des ligues de son ressort pour une
     * Fédération) — sans les élèves affiliés à un club, contrairement à
     * studentsVisiblesPar(). Utilisé pour l'écran de gestion dédié à ces
     * athlètes (saisie manuelle à Ligue/Fédération, donc plus sujette à
     * erreur qu'une inscription club classique — d'où le besoin d'édition/
     * suppression à ce niveau).
     */
    private function independantsVisiblesPar(string $activeId, string $activeType, ?string $q = null)
    {
        $organisateurs = [[$activeType, $activeId]];
        if ($activeType === 'Federation') {
            foreach (League::where('federation_id', $activeId)->pluck('id') as $ligueId) {
                $organisateurs[] = ['Ligue', $ligueId];
            }
        }

        $independants = Student::query()->where(function ($query) use ($organisateurs) {
            foreach ($organisateurs as [$type, $id]) {
                $query->orWhere(function ($q2) use ($type, $id) {
                    $q2->where('organisateur_type', $type)->where('organisateur_id', $id);
                });
            }
        });

        if ($q) {
            $independants->where('students.fullname', 'LIKE', '%' . $q . '%');
        }

        return $independants->get();
    }
}
