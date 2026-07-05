<?php

namespace App\Http\Controllers;

use App\Models\Saison;
use App\Models\Licence;
use App\Models\Student;
use App\Models\LicenceType;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class LicenceController extends Controller
{
    /**
     * Liste des types de licences disponibles pour le club connecté,
     * proposés par la fédération de sa ligue, pour la saison active.
     * Utilisé pour peupler le formulaire d'achat côté Club.
     */
    public function typesDisponibles(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier le club connecté.'
            ], 403);
        }

        $club = \App\Models\Club::find($activeId);

        if (!$club || !$club->league_id) {
            return response()->json([
                'success' => false,
                'message' => 'Ce club n\'est rattaché à aucune ligue.'
            ], 422);
        }

        $league = \App\Models\League::find($club->league_id);

        if (!$league || !$league->federation_id) {
            return response()->json([
                'success' => false,
                'message' => 'Votre ligue n\'est rattachée à aucune fédération.'
            ], 422);
        }

        $saisonActive = Saison::where('active', true)->first();

        if (!$saisonActive) {
            return response()->json(['message' => 'Aucune saison active trouvée'], 422);
        }

        $types = LicenceType::where('federation_id', $league->federation_id)
            ->where('saison_id', $saisonActive->id)
            ->orderBy('nom')
            ->get();

        if ($types->isEmpty()) {
            return response()->json([
                'success' => true,
                'data'    => [],
                'message' => 'Votre fédération n\'a pas encore défini de tarif de licence pour cette saison.'
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $types,
        ]);
    }

    /**
     * Achète une ou plusieurs licences pour des élèves du club connecté.
     * Body attendu : { items: [{ student_id, licence_type_id }, ...] }
     *
     * Vérifie :
     * - le club appartient bien à la fédération qui propose ce type de licence (via sa ligue)
     * - chaque student appartient bien à ce club
     * - chaque student n'a pas déjà une licence pour cette saison (contrainte unique student/saison)
     */
    public function store(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un club peut acheter des licences.'
            ], 403);
        }

        $club = \App\Models\Club::find($activeId);

        if (!$club) {
            return response()->json(['message' => 'Club introuvable.'], 404);
        }

        $league = \App\Models\League::find($club->league_id);

        if (!$league || !$league->federation_id) {
            return response()->json([
                'success' => false,
                'message' => 'Votre ligue n\'est rattachée à aucune fédération.'
            ], 422);
        }

        $federationId = $league->federation_id;

        $saisonActive = Saison::where('active', true)->first();

        if (!$saisonActive) {
            return response()->json(['message' => 'Aucune saison active trouvée'], 422);
        }

        $validated = $request->validate([
            'items'                     => 'required|array|min:1',
            'items.*.student_id'        => 'required|uuid|exists:students,id',
            'items.*.licence_type_id'   => 'required|uuid',
        ]);

        $studentIds = array_column($validated['items'], 'student_id');
        $typeIds    = array_unique(array_column($validated['items'], 'licence_type_id'));

        // Vérifie que tous les élèves appartiennent bien à ce club
        $validStudentIds = Student::whereHas('clubs', function ($q) use ($club) {
            $q->where('club_id', $club->id);
        })
            ->whereIn('id', $studentIds)
            ->pluck('id')
            ->toArray();

        if (count($validStudentIds) !== count(array_unique($studentIds))) {
            return response()->json([
                'success' => false,
                'message' => 'Un ou plusieurs élèves sélectionnés n\'appartiennent pas à votre club.'
            ], 403);
        }

        // Vérifie que tous les types de licence appartiennent bien à la fédération de la ligue du club
        $validTypes = LicenceType::where('federation_id', $federationId)
            ->where('saison_id', $saisonActive->id)
            ->whereIn('id', $typeIds)
            ->get()
            ->keyBy('id');

        if ($validTypes->count() !== count($typeIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Un ou plusieurs types de licence ne sont pas proposés par votre fédération.'
            ], 403);
        }

        // Vérifie qu'aucun élève n'a déjà une licence pour cette saison
        $alreadyLicensed = Licence::where('saison_id', $saisonActive->id)
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id');

        if ($alreadyLicensed->isNotEmpty()) {
            $names = Student::whereIn('id', $alreadyLicensed)->pluck('fullname')->implode(', ');
            return response()->json([
                'success' => false,
                'message' => "Déjà licencié(s) cette saison : $names"
            ], 422);
        }
        $lot = null;
        $licences = DB::transaction(function () use ($validated, $validTypes, $club, $federationId, $saisonActive, &$lot) {
            $created = [];

            foreach ($validated['items'] as $item) {
                $type = $validTypes->get($item['licence_type_id']);

                $created[] = Licence::create([
                    'saison_id'       => $saisonActive->id,
                    'club_id'         => $club->id,
                    'student_id'      => $item['student_id'],
                    'federation_id'   => $federationId,
                    'licence_type_id' => $type->id,
                    'montant_paye'    => $type->tarif,
                    'numero'          => null,
                    'status'          => Licence::STATUS_EN_ATTENTE,
                ]);
            }

            // Créer un nouveau lot de paiement pour ces licences
            $montantLot = collect($created)->sum('montant_paye');

            $lot = \App\Models\LicencePayment::create([
                'saison_id'     => $saisonActive->id,
                'federation_id' => $federationId,
                'club_id'       => $club->id,
                'amount'        => $montantLot,
                'status'        => 'pending',
            ]);

            // Lier chaque licence créée à ce lot
            foreach ($created as $licence) {
                \App\Models\LicencePaymentItem::create([
                    'licence_payment_id' => $lot->id,
                    'licence_id'         => $licence->id,
                ]);
            }

            return $created;
        });

        return response()->json([
            'success' => true,
            'message' => 'Licence(s) créée(s) avec succès.',
            'data'    => $licences,
            'payment' => $lot,
        ], 201);
    }

    /**
     * Génère un numéro de licence unique, séquentiel par saison.
     * Format : LIC-{ANNEE_DEBUT_SAISON}-XXXX
     */
    public static function genererNumero(Saison $saison): string
    {
        $prefix = 'LIC-' . \Carbon\Carbon::parse($saison->dateDebut)->format('Y') . '-';

        $sequenceNumber = Licence::where('saison_id', $saison->id)
            ->where('numero', 'LIKE', "{$prefix}%")
            ->count() + 1;

        return $prefix . str_pad($sequenceNumber, 4, '0', STR_PAD_LEFT);
    }
    public function mesEtudiants(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier le club connecté.'
            ], 403);
        }

        //  On liste les élèves (majeurs comme mineurs) inscrits dans ce club
        $students = Student::query()
            ->join('club_students', 'club_students.student_id', '=', 'students.id')
            ->where('club_students.club_id', $activeId)
            ->whereNull('club_students.deleted_at') // Exclure si l'élève a été retiré du club
            ->select('students.id', 'students.fullname', 'students.birthdate', 'students.sex')
            ->orderBy('students.fullname')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }

    /**
     * Liste des licences de l'élève (karateka) connecté, toutes saisons confondues.
     */
    public function mesLicencesEtudiant(Request $request)
    {
        $role = $request->attributes->get('role');

        if ($role !== 'karateka') {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux élèves.'
            ], 403);
        }

        $student = Student::where('user_id', auth()->id())->first();

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Profil élève introuvable.'
            ], 404);
        }

        $licences = $student->licences()
            ->with(['licenceType', 'saison', 'club:id,name,logo'])
            ->latest('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $licences,
        ]);
    }

    /**
     * Liste des licences du club connecté pour la saison active.
     */
    public function mesLicences(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier le club connecté.'
            ], 403);
        }

        $licences = Licence::with(['student', 'licenceType', 'saison'])
            ->where('club_id', $activeId)
            ->latest('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $licences,
        ]);
    }

    /**
     * Annule une licence — uniquement par le club propriétaire,
     * et seulement si elle n'a pas encore été validée par la fédération.
     */
    public function destroy(Request $request, Licence $licence)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club' || $licence->club_id !== $activeId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à annuler cette licence.'
            ], 403);
        }

        if ((int) $licence->status === Licence::STATUS_VALIDE) {
            return response()->json([
                'success' => false,
                'message' => 'Cette licence est déjà validée et ne peut plus être annulée.'
            ], 422);
        }

        $saisonId     = $licence->saison_id;
        $federationId = $licence->federation_id;
        $clubId       = $licence->club_id;

        // Retrouver le lot de paiement auquel appartient cette licence
        $item = \App\Models\LicencePaymentItem::where('licence_id', $licence->id)->first();

        $licence->delete(); // softDelete — cascade supprime l'item automatiquement

        // Si le lot existe et n'est pas encore payé, recalculer son montant
        if ($item) {
            $lot = \App\Models\LicencePayment::find($item->licence_payment_id);

            if ($lot && $lot->status !== 'paid') {
                $nouveauMontant = \App\Models\LicencePaymentItem::where('licence_payment_id', $lot->id)
                    ->join('licences', 'licences.id', '=', 'licence_payment_items.licence_id')
                    ->whereNull('licences.deleted_at') // respecte le softDelete
                    ->sum('licences.montant_paye');

                if ($nouveauMontant <= 0) {
                    // Plus aucune licence dans ce lot — on supprime le lot aussi
                    $lot->delete();
                } else {
                    $lot->update([
                        'amount' => $nouveauMontant,
                        // Si déclaré et montant change → repasse en pending
                        // pour que le club re-déclare avec le bon montant
                        'status' => $lot->status === 'declared' ? 'pending' : $lot->status,
                        'declared_at' => $lot->status === 'declared' ? null : $lot->declared_at,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Licence annulée.'
        ]);
    }
}
