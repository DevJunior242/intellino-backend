<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Combat;
use App\Models\OrdrePassage;
use Illuminate\Http\Request;
use App\Models\RotationArbitre;
use App\Models\ArbitreCompetition;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ArbitreStatsController extends Controller
{
    public function statsCarriere(Request $request)
    {
        $start = microtime(true);
        $user = Auth::user();

        // Tous les arbitre_competition_id de cet arbitre
        $arbitreCompIds = ArbitreCompetition::where('user_id', $user->id)
            ->pluck('id');

        // Toutes les rotations (séances) de cet arbitre
        $rotationIds = RotationArbitre::whereIn('arbitre_competition_id', $arbitreCompIds)
            ->pluck('id');

        // ── Métriques globales ─────────────────────────────────────────────

        $nbCompetitions = ArbitreCompetition::where('user_id', $user->id)
            ->count();

        $nbSeances = RotationArbitre::whereIn('arbitre_competition_id', $arbitreCompIds)
            ->count();

        $nbNotes = Note::whereIn('rotation_arbitre_id', $rotationIds)->count();

        $nbAthletesJuges = Note::whereIn('rotation_arbitre_id', $rotationIds)
            ->distinct('ordre_passage_id')
            ->count('ordre_passage_id');

        // ── Qualité de notation ────────────────────────────────────────────

        $noteMoyenne = Note::whereIn('rotation_arbitre_id', $rotationIds)
            ->avg('valeur');

        $noteMax = Note::whereIn('rotation_arbitre_id', $rotationIds)
            ->max('valeur');

        $noteMin = Note::whereIn('rotation_arbitre_id', $rotationIds)
            ->min('valeur');

        // Écart-type (constance) — calculé en SQL
        $ecartType = Note::whereIn('rotation_arbitre_id', $rotationIds)
            ->selectRaw('STDDEV(valeur) as ecart')
            ->value('ecart');

        // Taux de complétion : notes données / passages attendus
        // Un arbitre doit noter chaque passage dans sa rotation
        $passagesAttendus = OrdrePassage::whereIn(
            'config_notation_id',
            RotationArbitre::whereIn('arbitre_competition_id', $arbitreCompIds)
                ->pluck('config_notation_id')
        )
            ->whereIn('status', [OrdrePassage::STATUS_STARTED, OrdrePassage::STATUS_FINISHED])
            ->count();

        $tauxCompletion = $passagesAttendus > 0
            ? round(($nbNotes / $passagesAttendus) * 100, 1)
            : 100;

        // ── Répartition par rôle ───────────────────────────────────────────

        $nbJuge = RotationArbitre::whereIn('arbitre_competition_id', $arbitreCompIds)
            ->where('est_superviseur', false)
            ->count();

        $nbSuperviseur = RotationArbitre::whereIn('arbitre_competition_id', $arbitreCompIds)
            ->where('est_superviseur', true)
            ->count();

        // ── Activité par année ─────────────────────────────────────────────

        $parAnnee = ArbitreCompetition::where('user_id', $user->id)
            ->selectRaw('YEAR(created_at) as annee, COUNT(*) as nb')
            ->groupBy('annee')
            ->orderBy('annee')
            ->get()
            ->map(fn($row) => [
                'annee' => $row->annee,
                'nb'    => $row->nb,
            ]);

        // ── Dernière compétition ───────────────────────────────────────────

        $derniereRotation = RotationArbitre::whereIn('arbitre_competition_id', $arbitreCompIds)
            ->with([
                'configNotation.competition',
                'configNotation.competition.evenement',

            ])
            ->latest()
            ->first();

        $derniereComp = null;
        if ($derniereRotation) {
            $cfg = $derniereRotation->configNotation;

            $notesSession = Note::where('rotation_arbitre_id', $derniereRotation->id)->pluck('valeur')
                ->map(fn($v) => (float) $v);

            $derniereComp = [
                'nom'              => $cfg?->competition?->evenement?->nom ?? '—',
                'date'             => $cfg?->created_at?->format('d M Y') ?? '—',
                'plateau'          => $cfg?->plateau?->nom ?? '—',
                'role'             => $derniereRotation->est_superviseur
                    ? 'Superviseur'
                    : 'Juge ' . ($derniereRotation->poste ?? '—'),
                'passages_juges' => $notesSession->count(),
                'notes_saisies'  => $notesSession->count(),
                'note_moyenne'   => round($notesSession->avg() ?? 0, 2),
            ];
        }

        // ── Décisions du superviseur spécifiques au duel Kata (Art. 5.11 /
        // 3.5 WKF) ── Approximation : rattachées aux configs où cet arbitre
        // a été superviseur à un moment donné — ni Combat ni OrdrePassage
        // ne trace précisément qui a cliqué Hantei/disqualifié.
        $configsSuperviseur = RotationArbitre::whereIn('arbitre_competition_id', $arbitreCompIds)
            ->where('est_superviseur', true)
            ->pluck('config_notation_id');

        $nbHanteiTranches = Combat::whereIn('config_notation_id', $configsSuperviseur)
            ->where('type_victoire', 'hantei')
            ->count();

        $nbDisqualificationsBunkai = OrdrePassage::whereIn('config_notation_id', $configsSuperviseur)
            ->where('disqualifie_bunkai', true)
            ->count();

        // ── Section Kumite (à ajouter plus tard) ───────────────────────────
        // Ce dashboard ne compte aujourd'hui que l'activité Kata (via
        // Note/OrdrePassage) — le Kumite se score via CombatAction/Combat
        // directement, sans passer par Note. Un arbitre qui n'a jamais jugé
        // de Kata verra donc tout à zéro ici même s'il a beaucoup arbitré de
        // Kumite. Prévoir un bloc `kumite` symétrique (combats arbitrés,
        // hansoku/hantei tranchés en tant que superviseur, etc.) une fois le
        // besoin confirmé — même schéma RotationArbitre/ArbitreCompetition,
        // juste une source d'activité différente (CombatAction/Combat au
        // lieu de Note/OrdrePassage).

        // ── Première compétition (ancienneté) ─────────────────────────────

        $premiereComp = ArbitreCompetition::where('user_id', $user->id)
            ->oldest()
            ->first();

        $depuisAnnee = $premiereComp
            ? $premiereComp->created_at->format('Y')
            : now()->format('Y');
        Log::info('Temps execution', ['ms' => round((microtime(true) - $start) * 1000, 2)]);
        // ── Réponse ────────────────────────────────────────────────────────

        return response()->json([
            'success' => true,
            'stats'   => [
                'global' => [
                    'competitions'    => $nbCompetitions,
                    'seances'         => $nbSeances,
                    'athletes_juges'  => $nbAthletesJuges,
                    'notes_donnees'   => $nbNotes,
                    'depuis'          => $depuisAnnee,
                ],
                'qualite' => [
                    'note_moyenne'    => round($noteMoyenne ?? 0, 2),
                    'note_max'        => round($noteMax ?? 0, 2),
                    'note_min'        => round($noteMin ?? 0, 2),
                    'ecart_type'      => round($ecartType ?? 0, 2),
                    'taux_completion' => $tauxCompletion,
                ],
                'roles' => [
                    'juge'        => $nbJuge,
                    'superviseur' => $nbSuperviseur,
                ],
                // Décisions de superviseur propres au duel Kata — vide/zéro
                // pour un arbitre qui n'a fait que du Kumite jusqu'ici.
                'kata_duel' => [
                    'hantei_tranches'          => $nbHanteiTranches,
                    'disqualifications_bunkai' => $nbDisqualificationsBunkai,
                ],
                'par_annee'         => $parAnnee,
                'derniere_comp'     => $derniereComp,
            ],

        ]);
    }
}
