<?php

namespace App\Http\Controllers;

use App\Models\Jury;
use App\Models\User;
use App\Models\Poste;
use App\Models\Mandat;
use App\Models\Candidat;
use App\Models\BureauMembre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GovernanceController extends Controller
{
    // Pour le Mandat
    public function storeMandat(Request $request)
    {
        $data = $request->validate([
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'actif' => 'boolean'
        ], [
            'start_at.date' => 'Le début doit être une date valide',
            'end_at.date' => 'La fin doit être une date valide',
            'end_at.after' => 'La fin doit être après le début'
        ]);

        if ($request->actif == true) {
            Mandat::where('actif', true)->update(['actif' => false]);
        }
        $mandat = Mandat::create($data);

        return response()->json([
            'success' => true,
            'mandat' => $mandat,
            'message' => 'Le mandat a bien été créé'
        ]);
    }

    // Pour le Poste
    public function storePoste(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|unique:postes',
            'parent_id' => 'nullable|uuid|exists:postes,id',
        ]);

        if ($request->parent_id) {
            // CAS ADJOINT : On récupère le rang du parent et on fait +1
            $parent = Poste::findOrFail($request->parent_id);
            $data['rang'] = $parent->rang + 1;
        } else {
            // CAS TITULAIRE : On cherche le plus haut rang actuel
            $maxRang = Poste::whereNull('parent_id')->max('rang');
            // Si c'est le premier, on commence à 10, sinon on saute de 10
            $data['rang'] = $maxRang ? (floor($maxRang / 10) + 1) * 10 : 10;
        }

        $poste = Poste::create($data);

        return response()->json([
            'success' => true,
            'poste' => $poste,
            'message' => "Le poste {$poste->title} a été créé avec le rang {$poste->rang}"
        ]);
    }

    public function getPostes()
    {
        $postes = Poste::with('parent:id,title')->orderBy('rang', 'asc')->get();
        return response()->json($postes);
    }

    public function getMandats()
    {
        $mandats = Mandat::select('id', 'start_at', 'end_at', 'actif')
            ->where('actif', true)
            ->get();
        return response()->json($mandats);
    }

    public function searchUsers(Request $request)
    {
        $search = $request->query('q');

        return User::select('id', 'fullname', 'phone')
            ->where(function ($query) use ($search) {
                $query->where('fullname', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get();
    }
    public function store(Request $request)
    {
        $user = auth()->user();

        // On récupère l'accréditation du jury
        $jury = Jury::where('mandat_id', $request->mandat_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$jury) {
            return response()->json(['message' => 'Accès interdit : vous n\'êtes pas jury.'], 403);
        }

        return DB::transaction(function () use ($request, $jury) {
            foreach ($request->nominations as $nomination) {
                BureauMembre::create([
                    'candidat_id' => $nomination['candidat_id'],
                    'jury_id' => $jury->id, // On lie l'élu au jury responsable
                    'date_nomination' => now(),
                ]);

                // On marque le candidat comme "Élu"
                Candidat::where('id', $nomination['candidat_id'])
                    ->update(['est_elu' => true]);
            }

            // Le jury a fini sa mission
            $jury->update(['a_valide' => true]);

            return response()->json([
                'success' => true,

                'message' => 'Bureau validé avec succès !'
            ]);
        });
    }
}
