<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Stage;
use Illuminate\Http\Request;
use App\Models\StageRegistration;
use Illuminate\Support\Facades\DB;

class StageRegistrationController extends Controller
{
    /**
     * Inscrit un ou plusieurs pratiquants (users existants) du club connecté
     * à un stage organisé par la ligue de ce club.
     *
     * Vérifications :
     * - le club est bien identifié via le middleware
     * - le stage est organisé par LA ligue à laquelle ce club appartient
     * - chaque user_id envoyé appartient réellement à ce club
     * - aucun des users n'est déjà inscrit à ce stage
     */
    public function store(Request $request, Stage $stage)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier l\'organisateur connecté.'
            ], 403);
        }

        if ($activeType !== 'Club') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un club peut inscrire des pratiquants à un stage.'
            ], 403);
        }

        $club = \App\Models\Club::find($activeId);

        if (!$club) {
            return response()->json(['message' => 'Club introuvable.'], 404);
        }

        // Le stage doit être organisé par la ligue à laquelle ce club est rattaché
        if ($stage->organisateur_type !== 'Ligue' || $stage->organisateur_id !== $club->league_id) {
            return response()->json([
                'success' => false,
                'message' => 'Ce stage n\'est pas organisé par votre ligue.'
            ], 403);
        }

        $validated = $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'required|exists:users,id',
        ]);

        // Vérifie l'appartenance au club ET le rôle (exclut les parents),
        // via la table pivot club_users — un user peut appartenir à plusieurs clubs,
        // donc on ne se fie jamais à un champ statique sur users.
        $validUserIds = DB::table('club_users')
            ->join('roles', 'roles.id', '=', 'club_users.role_id')
            ->where('club_users.club_id', $club->id)
            ->whereNull('club_users.deleted_at')
            ->where('roles.name', '!=', 'parent')
            ->whereIn('club_users.user_id', $validated['user_ids'])
            ->pluck('club_users.user_id')
            ->toArray();

        if (count($validUserIds) !== count($validated['user_ids'])) {
            return response()->json([
                'success' => false,
                'message' => 'Un ou plusieurs pratiquants sélectionnés ne sont pas éligibles.'
            ], 403);
        }

        $users = User::whereIn('id', $validUserIds)->get();

        // Empêche les doublons : users déjà inscrits à ce stage
        $alreadyRegistered = StageRegistration::where('stage_id', $stage->id)
            ->whereIn('user_id', $validUserIds)
            ->pluck('user_id');

        if ($alreadyRegistered->isNotEmpty()) {
            $names = $users->whereIn('id', $alreadyRegistered)
                ->pluck('fullname')
                ->implode(', ');

            return response()->json([
                'success' => false,
                'message' => "Déjà inscrit(s) à ce stage : $names"
            ], 422);
        }

        $lot = null;

        $registrations = DB::transaction(function () use ($stage, $club, $validUserIds, &$lot) {
            $created = [];

            foreach ($validUserIds as $userId) {
                $created[] = StageRegistration::create([
                    'stage_id'   => $stage->id,
                    'user_id'    => $userId,
                    'club_id'    => $club->id,
                    'is_present' => false,
                ]);
            }

            // Créer une nouvelle transaction pour ces inscriptions
            $montantLot = $stage->price * count($created);

            $lot = \App\Models\Transaction::create([
                'club_id'           => $club->id,
                'organisateur_id'   => $stage->organisateur_id,
                'organisateur_type' => $stage->organisateur_type,
                'saison_id'         => $stage->saison_id,
                'payable_type'      => \App\Models\Transaction::PAYABLE_STAGE,
                'payable_id'        => $stage->id,
                'amount'            => $montantLot,
                'status'            => 'pending',
            ]);

            // Lier chaque inscription à cette transaction
            foreach ($created as $registration) {
                \App\Models\TransactionItem::create([
                    'transaction_id' => $lot->id,
                    'itemable_type'  => \App\Models\TransactionItem::ITEMABLE_STAGE_REGISTRATION,
                    'itemable_id'    => $registration->id,
                ]);
            }

            return $created;
        });

        $lot->setRelation('stage', $stage);

        return response()->json([
            'success' => true,
            'message' => 'Inscription envoyée avec succès !',
            'data'    => $registrations,
            'payment' => $lot,
        ], 201);
    }

    /**
     * Retourne les IDs des stages auxquels au moins un pratiquant
     * du club connecté est déjà inscrit (pour griser le bouton "S'inscrire").
     */
    public function mesInscriptions(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club') {
            return response()->json(['success' => true, 'data' => []]);
        }

        $stageIds = StageRegistration::where('club_id', $activeId)
            ->pluck('stage_id')
            ->unique()
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $stageIds,
        ]);
    }

    /**
     * Liste des pratiquants éligibles à une inscription de stage
     * (élèves, instructeurs) — exclut explicitement les parents.
     */
    public function mesPratiquants(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier le club connecté.'
            ], 403);
        }

        $users = User::query()
            ->join('club_users', 'club_users.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'club_users.role_id')
            ->where('club_users.club_id', $activeId)
            ->whereNull('club_users.deleted_at')
            ->where('roles.name', '!=', 'parent')
            ->when($request->filled('stage_id'), function ($query) use ($request, $activeId) {
                $query->whereNotIn('users.id', StageRegistration::where('stage_id', $request->input('stage_id'))
                    ->where('club_id', $activeId)
                    ->pluck('user_id'));
            })
            ->select('users.id', 'users.fullname', 'users.email', 'roles.name as role')
            ->orderBy('users.fullname')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    /**
     * Pour le dashboard Ligue : liste des inscrits à un stage donné
     * (émargement, suivi des paiements).
     */
    public function parStage(Request $request, Stage $stage)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($stage->organisateur_id !== $activeId || $stage->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à consulter ces inscriptions.'
            ], 403);
        }

        $registrations = StageRegistration::with(['user', 'club'])
            ->where('stage_id', $stage->id)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $registrations,
        ]);
    }

    /**
     * Émargement : bascule la présence d'un pratiquant (présent <-> absent).
     * Toggle plutôt que sens unique, pour corriger un clic accidentel.
     */
    public function togglePresence(Request $request, StageRegistration $registration)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $stage = $registration->stage;

        if ($stage->organisateur_id !== $activeId || $stage->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.'
            ], 403);
        }

        $registration->update(['is_present' => !$registration->is_present]);

        return response()->json([
            'success' => true,
            'message' => $registration->is_present ? 'Présence enregistrée.' : 'Présence annulée.',
            'data'    => $registration,
        ]);
    }

    /**
     * Paiement manuel (pas d'intégration de paiement en ligne pour l'instant) :
     * la Ligue bascule le statut de paiement d'une inscription.
     */
    public function togglePayment(Request $request, StageRegistration $registration)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $stage = $registration->stage;

        if ($stage->organisateur_id !== $activeId || $stage->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.'
            ], 403);
        }

        // togglePaymentStatus supprimé — le paiement est géré via Transaction/TransactionItem
        return response()->json([
            'success' => false,
            'message' => 'Utilisez la route de paiement dédiée.'
        ], 422);
    }

    /**
     * Désinscription par le club lui-même ou par l'organisateur du stage.
     * Recalcule le lot de paiement concerné via stage_payment_items.
     */
    public function destroy(Request $request, StageRegistration $registration)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $stage = $registration->stage;

        $estLeClub = $activeType === 'Club' && $registration->club_id === $activeId;
        $estOrganisateur = $stage &&
            $stage->organisateur_id === $activeId &&
            $stage->organisateur_type === $activeType;

        if (!$estLeClub && !$estOrganisateur) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à annuler cette inscription.'
            ], 403);
        }

        // Le club ne peut annuler que si le stage n'a pas commencé
        if ($estLeClub && $stage && now()->startOfDay()->greaterThanOrEqualTo($stage->start_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Ce stage a déjà commencé, l\'inscription ne peut plus être annulée.'
            ], 422);
        }

        // Retrouver la transaction liée via transaction_items
        $item = \App\Models\TransactionItem::where('itemable_type', \App\Models\TransactionItem::ITEMABLE_STAGE_REGISTRATION)
            ->where('itemable_id', $registration->id)
            ->first();

        $registration->delete();

        // itemable_id est polymorphique (pas de vraie contrainte FK possible),
        // donc pas de cascade DB automatique comme avec l'ancien
        // stage_registration_id : on supprime l'item explicitement.
        $item?->delete();

        // Recalculer la transaction concernée si non encore payée
        if ($item) {
            $lot = \App\Models\Transaction::find($item->transaction_id);

            if ($lot && $lot->status !== 'paid') {
                $nouveauMontant = \App\Models\TransactionItem::where('transaction_id', $lot->id)
                    ->where('itemable_type', \App\Models\TransactionItem::ITEMABLE_STAGE_REGISTRATION)
                    ->join('stage_registrations', 'stage_registrations.id', '=', 'transaction_items.itemable_id')
                    ->count() * $stage->price;

                if ($nouveauMontant <= 0) {
                    $lot->delete();
                } else {
                    $lot->update([
                        'amount'     => $nouveauMontant,
                        'status'     => $lot->status === 'declared' ? 'pending' : $lot->status,
                        'declared_at' => $lot->status === 'declared' ? null : $lot->declared_at,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Inscription annulée avec succès.',
        ]);
    }

    /**
     * Liste des inscriptions du club connecté, toutes confondues
     * (tous stages), pour l'écran "Mes inscriptions" côté Club.
     */
    public function mesInscriptionsDetail(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club') {
            return response()->json(['success' => true, 'data' => []]);
        }

        $registrations = StageRegistration::with(['user', 'stage'])
            ->where('club_id', $activeId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $registrations,
        ]);
    }

    /**
     * Export PDF de la liste des inscrits à un stage, pour la Ligue
     * (émargement papier, archivage).
     */
    public function exportPdf(Request $request, Stage $stage)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($stage->organisateur_id !== $activeId || $stage->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à exporter ces inscriptions.'
            ], 403);
        }

        $registrations = StageRegistration::with(['user', 'club'])
            ->where('stage_id', $stage->id)
            ->get();

        $pdf = \PDF::loadView('exports.stage-registrations', [
            'stage'         => $stage,
            'registrations' => $registrations,
        ]);

        $filename = 'inscriptions-' . str($stage->title)->slug() . '.pdf';

        return $pdf->download($filename);
    }
}
