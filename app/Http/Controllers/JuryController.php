<?php

namespace App\Http\Controllers;

use App\Models\Jury;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class JuryController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'mandat_id' => 'required|uuid|exists:mandats,id',
            'role_jury' => 'required|string',
            'organisateur_id' => 'required|uuid',
            'organisateur_type' => 'required|string|in:Ligue,Federation',
        ]);

        $userId = auth()->id();

        // 1. Vérifier s'il est déjà dans le jury pour ce mandat
        $exists = Jury::where('mandat_id', $request->mandat_id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return response()->json(['errors' => ['general' => 'Vous êtes déjà inscrit pour ce jury.']], 422);
        }

        // 2. Création
        Jury::create([
            'user_id' => $userId,
            'mandat_id' => $request->mandat_id,
            'role_jury' => $request->role_jury,
            'organisateur_id' => $request->organisateur_id,
            'organisateur_type' => $request->organisateur_type,
            'a_valide' => false
        ]);

        return response()->json(['success' => true, 'message' => 'Inscription réussie']);
    }

    public function myOrganization(Request $request)
    {
        // On récupère l'accréditation active de l'utilisateur connecté
        $jury = Jury::where('user_id', auth()->id())
            ->where('mandat_id', $request->mandat_id)
            ->first();

        if (!$jury) {
            return response()->json(['error' => 'Aucune mission de jury trouvée'], 404);
        }

        // On retourne l'ID et le Type (Ligue ou Fédé)
        return response()->json([
            'org_id' => $jury->organisateur_id,
            'org_type' => $jury->organisateur_type,
            'role' => $jury->role_jury
        ]);
    }
}
