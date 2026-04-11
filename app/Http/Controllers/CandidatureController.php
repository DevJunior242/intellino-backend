<?php

namespace App\Http\Controllers;

use App\Models\Candidat;
use Illuminate\Http\Request;

class CandidatureController extends Controller
{

    public function index(Request $request)
    {

        $Candidats = Candidat::with(['user', 'mandat', 'poste'])
            ->when($request->mandat_id, function ($query, $mandatId) {
                return $query->where('mandat_id', $mandatId);
            })
            ->get();
        return response()->json($Candidats);
    }
    public function store(Request $request)
    {
        $request->validate([
            'mandat_id' => 'required|exists:mandats,id',
            'poste_id' => 'required|exists:postes,id',
        ]);

        $user = auth()->user();

        // Optionnel : Vérifier s'il est déjà candidat ailleurs pour ce mandat
        $dejaCandidat = Candidat::where('mandat_id', $request->mandat_id)
            ->where('user_id', $user->id)
            ->exists();

        if ($dejaCandidat) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà déposé une candidature pour ce mandat.'
            ], 422);
        }

        Candidat::create([
            'mandat_id' => $request->mandat_id,
            'poste_id' => $request->poste_id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Candidature enregistrée avec succès !'
        ]);
    }
}
