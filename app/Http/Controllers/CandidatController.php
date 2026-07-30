<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Examen;
use App\Models\Student;
use Illuminate\Http\Request;
use App\Models\ExamenPayment;
use App\Models\ExamenCandidat;
use App\Models\ExamenPaymentItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Http\Requests\StoreCandidatRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CandidatController extends Controller
{
    use AuthorizesRequests;

    /**
     * Inscrit plusieurs candidats (élèves du club connecté) à un examen,
     * en lot, avec un seul lot de paiement pour tout le groupe.
     *
     * Le club peut inscrire :
     * - à son propre examen (organisé par le club lui-même)
     * - à un examen organisé par sa ligue
     * - à un examen organisé par sa fédération (via sa ligue)
     */
    public function storeBatch(Request $request, Examen $examen)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un club peut inscrire des candidats en lot.'
            ], 403);
        }

        $club = Club::with('league')->find($activeId);

        if (!$club) {
            return response()->json(['message' => 'Club introuvable.'], 404);
        }

        $isOwner = $examen->organisateur_type === 'Club' && $examen->organisateur_id === $club->id;
        $isLeagueExam = $examen->organisateur_type === 'Ligue' && $examen->organisateur_id === $club->league_id;
        $isFederationExam = $examen->organisateur_type === 'Federation'
            && $club->league
            && $examen->organisateur_id === $club->league->federation_id;

        if (!$isOwner && !$isLeagueExam && !$isFederationExam) {
            return response()->json([
                'success' => false,
                'message' => 'Cet examen n\'est pas organisé par votre club, votre ligue ou votre fédération.'
            ], 403);
        }

        $validated = $request->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'required|uuid|exists:students,id',
        ]);

        // Vérifie que chaque élève appartient bien au club connecté
        $validStudentIds = Student::whereHas('clubs', function ($q) use ($club) {
            $q->where('club_id', $club->id);
        })->whereIn('id', $validated['student_ids'])
            ->pluck('id')
            ->toArray();

        if (count($validStudentIds) !== count($validated['student_ids'])) {
            return response()->json([
                'success' => false,
                'message' => 'Un ou plusieurs élèves sélectionnés n\'appartiennent pas à votre club.'
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

        $lot = null;

        $candidats = DB::transaction(function () use ($examen, $club, $validStudentIds, &$lot) {
            $created = [];

            foreach ($validStudentIds as $studentId) {
                $created[] = ExamenCandidat::create([
                    'examen_id' => $examen->id,
                    'student_id' => $studentId,
                    'club_id'   => $club->id,
                    'status'    => ExamenCandidat::STATUS_REGISTERED,
                ]);
            }

            // Un examen gratuit (price = 0) n'a pas besoin de lot de paiement
            if ((float) $examen->price <= 0) {
                return $created;
            }

            $montantLot = $examen->price * count($created);

            $lot = ExamenPayment::create([
                'examen_id' => $examen->id,
                'club_id'   => $club->id,
                'amount'    => $montantLot,
                'status'    => 'pending',
            ]);

            foreach ($created as $candidat) {
                ExamenPaymentItem::create([
                    'examen_payment_id'  => $lot->id,
                    'examen_candidat_id' => $candidat->id,
                ]);
            }

            return $created;
        });

        if ($lot) {
            $lot->setRelation('examen', $examen);
        }

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

            // 2. Définition des accès
            $isOwner = ($examen->organisateur_id === $activeId);

            // Un club peut inscrire si l'examen est organisé par une Ligue
            $isClubAccessingLeague = ($activeType === 'Club' && $examen->organisateur_type === 'Ligue');

            if (!$isOwner && !$isClubAccessingLeague) {
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



            $candidat = ExamenCandidat::create([
                'student_id' => $studentId,
                'examen_id' => $examenId,
                'status' => 'registered',
            ]);

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

            // Retrouver le lot de paiement lié via examen_payment_items
            $item = ExamenPaymentItem::where('examen_candidat_id', $examenCandidat->id)->first();

            $examenCandidat->delete();

            // Recalculer le lot concerné si non encore payé
            if ($item) {
                $lot = ExamenPayment::find($item->examen_payment_id);

                if ($lot && $lot->status !== 'paid') {
                    $nouveauMontant = ExamenPaymentItem::where('examen_payment_id', $lot->id)->count() * $examen->price;

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
