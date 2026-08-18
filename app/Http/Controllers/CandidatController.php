<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Examen;
use App\Models\Student;
use Illuminate\Http\Request;
use App\Models\ExamenCandidat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Http\Requests\StoreCandidatRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Controllers\Concerns\ResolvesVisibleStudents;

class CandidatController extends Controller
{
    use AuthorizesRequests;
    use ResolvesVisibleStudents;

    /**
     * Est-ce que l'organisateur connecté peut inscrire des candidats à cet
     * examen ? Soit c'est lui l'organisateur (Club/Ligue/Fédération sur son
     * propre examen), soit c'est un club inscrivant ses élèves à l'examen de
     * sa ligue ou de la fédération de sa ligue.
     */
    private function peutInscrireA(Examen $examen, ?string $activeId, ?string $activeType): bool
    {
        if (!$activeId || !$activeType) {
            return false;
        }

        if ($examen->organisateur_id === $activeId && $examen->organisateur_type === $activeType) {
            return true;
        }

        if ($activeType !== 'Club') {
            return false;
        }

        $club = Club::with('league')->find($activeId);

        if (!$club) {
            return false;
        }

        if ($examen->organisateur_type === 'Ligue' && $examen->organisateur_id === $club->league_id) {
            return true;
        }

        return $examen->organisateur_type === 'Federation'
            && $club->league
            && $examen->organisateur_id === $club->league->federation_id;
    }

    /**
     * Élèves éligibles à un examen pour l'organisateur connecté : visibles
     * selon la hiérarchie club/ligue/fédération, ayant exactement le grade
     * requis, et pas déjà inscrits — pour cocher directement les bons
     * candidats sans risquer un rejet après coup.
     */
    public function candidatsEligibles(Examen $examen, Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$this->peutInscrireA($examen, $activeId, $activeType)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas la permission de consulter les candidats de cet examen.'
            ], 403);
        }

        $alreadyRegisteredIds = ExamenCandidat::where('examen_id', $examen->id)->pluck('student_id');

        $visibleIds = $this->studentsVisiblesPar($activeId, $activeType)
            ->pluck('id')
            ->diff($alreadyRegisteredIds);

        $students = Student::with('currentGrade')
            ->whereIn('id', $visibleIds)
            ->get()
            ->filter(fn ($student) => $student->currentGrade?->current_grade_id === $examen->current_grade_id)
            ->sortBy('fullname')
            ->values()
            ->map(fn ($s) => [
                'id'        => $s->id,
                'fullname'  => $s->fullname,
                'birthdate' => $s->birthdate,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }

    /**
     * Inscrit plusieurs candidats à un examen en lot, avec une seule
     * transaction de paiement pour tout le groupe. Voir peutInscrireA()
     * pour les règles d'accès (Club/Ligue/Fédération sur leur propre
     * examen, ou Club inscrivant ses élèves à l'examen de sa ligue/fédé).
     */
    public function storeBatch(Request $request, Examen $examen)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$this->peutInscrireA($examen, $activeId, $activeType)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas la permission d\'inscrire des candidats à cet examen.'
            ], 403);
        }

        $validated = $request->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'required|uuid|exists:students,id',
        ]);

        // Vérifie que chaque élève est bien visible par l'organisateur connecté
        $visibleIds = $this->studentsVisiblesPar($activeId, $activeType)->pluck('id');
        $validStudentIds = $visibleIds->intersect($validated['student_ids'])->values()->toArray();

        if (count($validStudentIds) !== count($validated['student_ids'])) {
            return response()->json([
                'success' => false,
                'message' => 'Un ou plusieurs élèves sélectionnés ne vous sont pas accessibles.'
            ], 403);
        }

        $students = Student::with('currentGrade')->whereIn('id', $validStudentIds)->get();

        // Vérifie le grade requis pour chaque élève
        $ineligible = $students->filter(function ($student) use ($examen) {
            return $student->currentGrade?->current_grade_id !== $examen->current_grade_id;
        });

        if ($ineligible->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'N\'ont pas le grade requis : ' . $ineligible->pluck('fullname')->implode(', '),
            ], 422);
        }

        // Empêche les doublons : élèves déjà inscrits à cet examen
        $alreadyRegistered = ExamenCandidat::where('examen_id', $examen->id)
            ->whereIn('student_id', $validStudentIds)
            ->pluck('student_id');

        if ($alreadyRegistered->isNotEmpty()) {
            $names = $students->whereIn('id', $alreadyRegistered)->pluck('fullname')->implode(', ');

            return response()->json([
                'success' => false,
                'message' => "Déjà inscrit(s) à cet examen : $names"
            ], 422);
        }

        // Club responsable du paiement : le club connecté lui-même, ou — si
        // c'est la ligue/fédération qui inscrit directement des candidats à
        // son propre examen — le club de chaque élève (le plus souvent le
        // cas réel), sinon pas de transaction (athlète vraiment indépendant).
        $payingClubId = $activeType === 'Club' ? $activeId : null;

        $lot = null;

        $candidats = DB::transaction(function () use ($examen, $validStudentIds, $payingClubId, &$lot) {
            $created = [];

            foreach ($validStudentIds as $studentId) {
                $clubId = $payingClubId ?? Student::find($studentId)?->clubs()->value('clubs.id');

                $created[] = [
                    'candidat' => ExamenCandidat::create([
                        'examen_id' => $examen->id,
                        'student_id' => $studentId,
                        'club_id'   => $clubId,
                        'status'    => ExamenCandidat::STATUS_REGISTERED,
                    ]),
                    'club_id' => $clubId,
                ];
            }

            // Un examen gratuit (price = 0) n'a pas besoin de lot de paiement
            if ((float) $examen->price <= 0) {
                return array_column($created, 'candidat');
            }

            // Regroupe par club payeur : un club inscrivant en lot n'a qu'une
            // seule transaction, mais une ligue/fédération inscrivant des
            // élèves de plusieurs clubs différents a une transaction par club.
            $parClub = collect($created)->groupBy('club_id');

            foreach ($parClub as $clubId => $groupe) {
                if (!$clubId) {
                    continue; // athlète(s) sans club : pas de transaction
                }

                $lot = \App\Models\Transaction::create([
                    'club_id'           => $clubId,
                    'organisateur_id'   => $examen->organisateur_id,
                    'organisateur_type' => $examen->organisateur_type,
                    'saison_id'         => $examen->saison_id,
                    'payable_type'      => \App\Models\Transaction::PAYABLE_EXAMEN,
                    'payable_id'        => $examen->id,
                    'amount'            => $examen->price * $groupe->count(),
                    'status'            => 'pending',
                ]);

                foreach ($groupe as $item) {
                    \App\Models\TransactionItem::create([
                        'transaction_id' => $lot->id,
                        'itemable_type'  => \App\Models\TransactionItem::ITEMABLE_EXAMEN_CANDIDAT,
                        'itemable_id'    => $item['candidat']->id,
                    ]);
                }
            }

            return array_column($created, 'candidat');
        });

        return response()->json([
            'success' => true,
            'message' => 'Inscription envoyée avec succès !',
            'data'    => $candidats,
            'payment' => $lot,
        ], 201);
    }

    public function store(StoreCandidatRequest $request)
    {
        $validated = $request->validated();


        $candidat = ExamenCandidat::create($validated);

        return response()->json([
            'success' => true,
            'data' => $candidat,
            'message' => 'votre candidat a bien été créé'
        ], 201);
    }

    public function addCandidate(Examen $examen, Student $student, Request $request)
    {
        //$this->authorize('create', ExamenCandidat::class);
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        try {

            if (!$this->peutInscrireA($examen, $activeId, $activeType)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas la permission d\'inscrire des candidats à cet examen.'
                ], 403);
            }
            Log::info('student current grade id', ['student' => $student->currentGrade]);
            $studentCurrentGradeId = $student->currentGrade?->current_grade_id;
            // Si l'élève n'a pas exactement le grade requis par l'examen
            if ($studentCurrentGradeId !== $examen->current_grade_id) {
                return response()->json([
                    'success' => false,
                    'message' => "Ce candidat n'a pas le grade requis pour participer à cet examen. Grade requis : " . ($examen->currentGrade->name ?? 'Inconnu'),
                ], 422);
            }

            // --- VÉRIFICATION OPTIONNELLE : Déjà inscrit ? ---
            $alreadyRegistered = ExamenCandidat::where('examen_id', $examen->id)
                ->where('student_id', $student->id)
                ->exists();

            if ($alreadyRegistered) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet étudiant est déjà inscrit à cet examen.',
                ], 422);
            }
            $examenId = $examen->id;
            $studentId = $student->id;

            // Club responsable du paiement : le club connecté lui-même, ou —
            // si c'est la ligue/fédération qui ajoute directement un candidat
            // à son propre examen — le club auquel appartient déjà l'élève
            // (le plus souvent le cas réel). Reste null pour un athlète
            // vraiment indépendant : pas de transaction générée dans ce cas.
            $payingClubId = $activeType === 'Club'
                ? $activeId
                : $student->clubs()->value('clubs.id');

            $candidat = DB::transaction(function () use ($examenId, $studentId, $examen, $payingClubId) {
                $created = ExamenCandidat::create([
                    'student_id' => $studentId,
                    'examen_id' => $examenId,
                    'club_id' => $payingClubId,
                    'status' => 'registered',
                ]);

                // Même suivi de paiement qu'un ajout en lot (storeBatch) —
                // un examen payant génère une transaction même pour un ajout
                // un par un, y compris quand le club s'inscrit lui-même à son
                // propre examen (pour qu'il retrouve cette recette dans sa
                // Comptabilité).
                if ((float) $examen->price > 0 && $payingClubId) {
                    $transaction = \App\Models\Transaction::create([
                        'club_id'           => $payingClubId,
                        'organisateur_id'   => $examen->organisateur_id,
                        'organisateur_type' => $examen->organisateur_type,
                        'saison_id'         => $examen->saison_id,
                        'payable_type'      => \App\Models\Transaction::PAYABLE_EXAMEN,
                        'payable_id'        => $examen->id,
                        'amount'            => $examen->price,
                        'status'            => 'pending',
                    ]);

                    \App\Models\TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'itemable_type'  => \App\Models\TransactionItem::ITEMABLE_EXAMEN_CANDIDAT,
                        'itemable_id'    => $created->id,
                    ]);
                }

                return $created;
            });

            return response()->json([
                'success' => true,
                'data' => $candidat,
                'message' => 'votre candidat a bien été créé'
            ], 201);
        } catch (QueryException $e) {
            $errcode = $e->getCode();
            $errmessage = $e->getMessage();
            Log::error('erreur', ['code' => $errcode, 'message' => $errmessage]);
            //erreur 23000
            if ($errcode == '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'vous ne pouvez pas ajouter cet étudiant à cet examen.Il est déjà inscrit',
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la note',
            ], 500);
        }
    }

    //remove
    public function destroy(Examen $examen, ExamenCandidat $examenCandidat)

    {
        try {
            //examen ca
            $examenId = $examen->id;
            $examenCandidatId = $examenCandidat->student_id;
            Log::info('examenId', ['examenId' => $examenId]);
            Log::info('examenCandidatId', ['examenCandidatId' => $examenCandidatId]);
            if ($examenCandidat->examen_id !== $examenId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer cet étudiant car il n\'est pas inscrit à cet examen',
                ], 400);
            }

            $examenCandidat = ExamenCandidat::where('examen_id', $examenId)
                ->where('student_id', $examenCandidatId)
                ->first();
            if (!$examenCandidat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer cet étudiant car il n\'est pas inscrit à cet examen',
                ], 400);
            }

            // Retrouver la transaction liée via transaction_items
            $item = \App\Models\TransactionItem::where('itemable_type', \App\Models\TransactionItem::ITEMABLE_EXAMEN_CANDIDAT)
                ->where('itemable_id', $examenCandidat->id)
                ->first();

            $examenCandidat->delete();

            // itemable_id est polymorphique (pas de vraie contrainte FK
            // possible), donc pas de cascade DB automatique comme avec
            // l'ancien examen_candidat_id : on supprime l'item explicitement.
            $item?->delete();

            // Recalculer la transaction concernée si non encore payée
            if ($item) {
                $lot = \App\Models\Transaction::find($item->transaction_id);

                if ($lot && $lot->status !== 'paid') {
                    $nouveauMontant = \App\Models\TransactionItem::where('transaction_id', $lot->id)
                        ->where('itemable_type', \App\Models\TransactionItem::ITEMABLE_EXAMEN_CANDIDAT)
                        ->count() * $examen->price;

                    if ($nouveauMontant <= 0) {
                        $lot->delete();
                    } else {
                        $lot->update([
                            'amount'      => $nouveauMontant,
                            'status'      => $lot->status === 'declared' ? 'pending' : $lot->status,
                            'declared_at' => $lot->status === 'declared' ? null : $lot->declared_at,
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Votre candidat a bien été supprimé'
            ], 200);
        } catch (\Throwable $th) {
            Log::error('erreur', ['erreur' => $th->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression du candidat'
            ], 500);
        }
    }
}
