<?php

namespace App\Http\Controllers;

use App\Models\Saison;
use App\Models\Licence;
use App\Models\LicenceType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LicenceTypeController extends Controller
{

    /**
     * Liste de tous les licenciés (toutes saisons confondues sur la saison active)
     * pour un type de licence précis — vue Fédération, depuis le bouton
     * "Voir les licenciés" sur chaque carte de type.
     */
    public function licencies(Request $request, LicenceType $licenceType)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($licenceType->federation_id !== $activeId || $activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à consulter ces licenciés.'
            ], 403);
        }

        $licences = Licence::with(['student', 'club'])
            ->where('licence_type_id', $licenceType->id)
            ->latest('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $licences,
        ]);
    }


    /*
    *
     * Liste des types de licences définis par la fédération connectée,
     * pour la saison active.
     */
    public function index(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une fédération peut gérer les types de licences.'
            ], 403);
        }

        $saisonActive = Saison::where('active', true)
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', 'Federation')
            ->first();

        if (!$saisonActive) {
            return response()->json(['message' => 'Aucune saison active trouvée'], 422);
        }

        $types = LicenceType::where('federation_id', $activeId)
            ->where('saison_id', $saisonActive->id)
            ->orderBy('nom')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $types,
        ]);
    }

    /**
     * Création d'un type de licence par la fédération, pour la saison active.
     */
    public function store(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une fédération peut créer un type de licence.'
            ], 403);
        }

        $saisonActive = Saison::where('active', true)
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', 'Federation')
            ->first();

        if (!$saisonActive) {
            return response()->json(['message' => 'Aucune saison active trouvée'], 422);
        }

        $validated = $request->validate([
            'code'  => 'required|string|max:30|alpha_dash',
            'nom'   => 'required|string|max:100',
            'tarif' => 'required|numeric|min:0',
        ]);

        // Empêche un doublon de code pour cette saison/fédération (cohérent avec la contrainte unique en base)
        $exists = LicenceType::where('saison_id', $saisonActive->id)
            ->where('federation_id', $activeId)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Un type de licence avec ce code existe déjà pour cette saison.'
            ], 422);
        }

        $type = LicenceType::create([
            'saison_id'     => $saisonActive->id,
            'federation_id' => $activeId,
            'code'          => $validated['code'],
            'nom'           => $validated['nom'],
            'tarif'         => $validated['tarif'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Type de licence créé avec succès.',
            'data'    => $type,
        ], 201);
    }

    /**
     * Mise à jour d'un type de licence (typiquement le tarif).
     */
    public function update(Request $request, LicenceType $licenceType)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($licenceType->federation_id !== $activeId || $activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.'
            ], 403);
        }

        $validated = $request->validate([
            'nom'   => 'sometimes|required|string|max:100',
            'tarif' => 'sometimes|required|numeric|min:0',
        ]);

        $licenceType->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Type de licence mis à jour.',
            'data'    => $licenceType,
        ]);
    }

    /**
     * Suppression d'un type de licence — seulement si aucune licence ne l'utilise déjà.
     */
    public function destroy(Request $request, LicenceType $licenceType)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($licenceType->federation_id !== $activeId || $activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.'
            ], 403);
        }

        if ($licenceType->licences()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce type de licence est déjà utilisé et ne peut pas être supprimé.'
            ], 422);
        }

        $licenceType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Type de licence supprimé.'
        ]);
    }
}
