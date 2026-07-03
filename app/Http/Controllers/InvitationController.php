<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Role;
use App\Models\League;
use App\Models\Federation;
use Illuminate\Http\Request;

class InvitationController extends Controller
{


    public function rejoindreLigue(Request $request)
    {
        // 1. On récupère le club actif (via ton middleware de contexte Club)
        $activeClubId = $request->attributes->get('organisateur_id');
        $club = Club::findOrFail($activeClubId);

        // 2. Validation du code d'invitation envoyé par le formulaire
        $validated = $request->validate([
            'invitation_code' => 'required|string',
        ]);

        // 3. On cherche la ligue qui possède ce code
        $league = League::where('invitation_code', $validated['invitation_code'])->first();

        if (!$league) {
            return response()->json(['message' => "Code d'invitation invalide."], 422);
        }

        // 4. Sécurité : Est-ce que le club est déjà dans une ligue ?
        if ($club->league_id !== null) {
            return response()->json(['message' => "Ce club est déjà affilié à une ligue."], 422);
        }


        $club->update([
            'league_id' => $league->id,
            // 'region'    => $league->region,
            // 'country_id' => $league->country_id,
        ]);

        // =========================================================================
        // 🎯 AJOUT : PRÉPARATION DU PACKAGE COMPLET POUR REACT
        // =========================================================================
        $user = $request->user();

        // On récupère tous les rôles indexés par leur ID pour ton helper
        $allRoles = Role::all()->keyBy('id');

        // Ta fonction de formatage fétiche
        $formatOrg = function ($org) use ($allRoles) {
            $roleId = $org->pivot->role_id ?? null;
            $roleName = $roleId ? ($allRoles->get($roleId)->name ?? null) : null;

            return [
                'id'              => $org->id,
                'name'            => $org->name,
                'league_id'       => $org->league_id,
                'league_name'     => $org->league ? $org->league->name : null,
                'federation_id'   => $org->federation_id,
                'invitation_code' => $org->invitation_code ?? null,
                'role'            => $roleName ? [$roleName] : [],
            ];
        };

        // On recharge les relations fraîchement modifiées en BDD
        $clubs = $user->clubs ? $user->clubs->map($formatOrg) : [];
        $leagues = $user->leagues ? $user->leagues->map($formatOrg) : [];

        // On renvoie TOUT à React d'un coup
        return response()->json([
            'success' => true,
            'message' => "Félicitations ! Votre club a rejoint la ligue : {$league->name}.",
            'user'    => $user,
            'clubs'   => $clubs,
            'leagues' => $leagues,
        ]);
    }



    public function rejoindreFederation(Request $request)
    {
        try {

            // 1. On récupère la ligue active
            $activeLeagueId = $request->attributes->get('organisateur_id');
            $league = League::findOrFail($activeLeagueId);

            // 2. Validation
            $validated = $request->validate([
                'invitation_code' => 'required|string',
            ]);

            // 3. Recherche de la fédération
            $federation = Federation::where('invitation_code', $validated['invitation_code'])->first();

            if (!$federation) {
                return response()->json([
                    'message' => "Code d'invitation invalide."
                ], 422);
            }

            // 4. Vérification
            if ($league->federation_id !== null) {
                return response()->json([
                    'message' => "Cette Ligue est déjà affiliée à une fédération."
                ], 422);
            }

            // 5. Mise à jour
            $league->update([
                'federation_id' => $federation->id,
                'country_id'    => $federation->country_id,
            ]);

            $user = $request->user();

            $allRoles = Role::all()->keyBy('id');

            $formatOrg = function ($org) use ($allRoles) {
                $roleId = $org->pivot->role_id ?? null;
                $roleName = $roleId ? ($allRoles->get($roleId)->name ?? null) : null;

                return [
                    'id'              => $org->id,
                    'name'            => $org->name,
                    'federation_id'   => $org->federation_id,
                    'federation_name' => $org->federation?->name,
                    'invitation_code' => $org->invitation_code ?? null,
                    'role'            => $roleName ? [$roleName] : [],
                ];
            };

            $clubs = $user->clubs ? $user->clubs->map($formatOrg) : [];
            $leagues = $user->leagues ? $user->leagues->map($formatOrg) : [];
            $federations = $user->federations ? $user->federations->map($formatOrg) : [];

            return response()->json([
                'success' => true,
                'message' => "Félicitations ! Votre ligue a rejoint la fédération : {$federation->name}.",
                'user' => $user,
                'clubs' => $clubs,
                'leagues' => $leagues,
                'federations' => $federations,
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }
}
