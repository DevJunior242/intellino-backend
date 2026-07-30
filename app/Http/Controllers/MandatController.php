<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\LeagueUser;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ActivationKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MandatController extends Controller
{
    public function transferMandate(Request $request)
    {
        // Action irréversible (révoque immédiatement le mandat de l'admin courant) :
        // on exige le mot de passe pour se prémunir d'une session/jeton volé.
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'email' => 'required|email',
            'mandate_end_at' => 'required|date|after:now',
        ]);

        $currentUser = auth()->user();
        $leagueId = $currentUser->current_league_id;

        return DB::transaction(function () use ($request, $currentUser, $leagueId) {

            // 1. On cherche ou on crée le compte du nouveau président
            $nextPresident = User::firstOrCreate(
                ['email' => $request->email],
                [
                    'phone' => $request->phone
                ],
                [
                    'fullname' => 'Nouveau Président',
                    'password' => Hash::make(Str::random(12)),
                ]
            );

            // 2. On expire le mandat de l'admin actuel (celui qui sort)
            DB::table('league_users')
                ->where('user_id', $currentUser->id)
                ->where('league_id', $leagueId)
                ->where('mandate_status', 1)
                ->update([
                    'mandate_status' => 0,
                    'mandate_end_at' => now(),
                ]);

            // 3. On détache sa session active de cette ligue
            $currentUser->update(['current_league_id' => null]);

            // 4. On attache le nouveau président à la ligue existante
            $adminRole = Role::where('name', 'admin')->first();

            $nextPresident = LeagueUser::create([
                'league_id' => $leagueId,
                'user_id' => $nextPresident->id,
                'role_id' => $adminRole->id,
                'mandate_start_at' => now(),
                'mandate_end_at' => $request->mandate_end_at,
                'mandate_status' => 1,
            ]);

            $nextPresident->update(['current_league_id' => $leagueId]);

            // 5. OPTIONNEL : Envoyer un mail automatique au nouveau président pour l'inviter à définir son mot de passe
            // Mail::to($nextPresident->email)->send(new NewMandateInvitationEmail($leagueId));

            return response()->json([
                'success' => true,
                'message' => 'Mandat transféré avec succès. Vos accès ont été révoqués au profit du nouveau président.'
            ]);
        });
    }
    public function takeoverLeague(Request $request)
    {
        $request->validate([
            'activation_key' => 'required|string',
            'league_id'      => 'required|string|exists:leagues,id', // On valide que la ligue existe
            'mandate_end_at' => 'required|date',
        ]);

        $user = auth()->user();

        return DB::transaction(function () use ($request, $user) {

            // SÉCURITÉ DOUBLE : On cherche la clé ET on vérifie qu'elle appartient bien à CETTE ligue précise
            $key = ActivationKey::where('key_code', $request->activation_key)
                ->where('target_league_id', $request->league_id) // L'ID envoyé doit matcher l'ID de la clé
                ->where('is_used', false)
                ->where('type', 'takeover_league')
                ->lockForUpdate()
                ->first();

            if (!$key) {
                return response()->json([
                    'success' => false,
                    'message' => 'La clé est invalide pour cette ligue, ou a déjà été utilisée.'
                ], 422);
            }

            $leagueId = $request->league_id;
            $oldUserIds = DB::table('league_users')
                ->where('league_id', $leagueId)
                ->where('mandate_status', 1)
                ->pluck('user_id');

            // 1. Expire tous les anciens mandats actifs pour cette ligue précise
            DB::table('league_users')
                ->where('league_id', $leagueId)
                ->where('mandate_status', 1)
                ->update([
                    'mandate_status' => 0,
                    'mandate_end_at' => now(),
                ]);
            if ($oldUserIds->isNotEmpty()) {
                DB::table('users')
                    ->whereIn('id', $oldUserIds)
                    ->where('current_league_id', $leagueId)
                    ->update(['current_league_id' => null]);
            }
            // 2. Attache le nouveau président
            $adminRole = Role::where('name', 'admin')->first();


            LeagueUser::create([
                'role_id' => $adminRole->id,
                'mandate_start_at' => now(),
                'mandate_end_at' => $request->mandate_end_at,
                'mandate_status' => 1,
                'league_id' => $leagueId,
                'user_id' => $user->id,
            ]);

            $user->update(['current_league_id' => $leagueId]);

            // 3. Consomme la clé
            $key->update([
                'is_used' => true,
                'used_at' => now(),
                'used_by_user_id' => $user->id,
                'used_by_organisation_id' => $leagueId,
            ]);
            // Rechargement complet de l'utilisateur avec ses relations à jour
            $user->load([
                'leagues' => function ($query) {
                    $query->wherePivot('mandate_status', 1)
                        ->with('roles');
                },
                'federations' => function ($query) {
                    $query->wherePivot('mandate_status', 1)
                        ->with('roles');
                },
                'clubs.roles',
            ]);


            // Re-génération de la liste des ligues au format attendu par ton Front
            $leagues = $user->leagues->map(function ($l) {
                return [
                    'id'   => $l->id,
                    'name' => $l->name,
                    'role' => $l->roles->pluck('name')->toArray(),
                ];
            });

            // Re-génération de la liste des clubs au format attendu par ton Front
            $clubs = $user->clubs->map(function ($c) {
                return [
                    'id'   => $c->id,
                    'name' => $c->name,
                    'role' => $c->roles->pluck('name')->toArray(),
                ];
            });

            // Fusionner TOUS les rôles de l'utilisateur (Clubs + Ligues) dans un tableau plat
            $allRoles = collect()
                ->concat($user->leagues->flatMap->roles->pluck('name'))
                ->concat($user->clubs->flatMap->roles->pluck('name'))
                ->unique()
                ->values()
                ->toArray();

            return response()->json([
                'success'    => true,
                'message'    => 'Reprise de la ligue effectuée avec succès.',
                'user'       => $user,
                'leagues'    => $leagues,
                'clubs'      => $clubs,
                'roles'      => $allRoles,
                'new_league' => [
                    'id'   => $leagueId,
                    'type' => 'Ligue',
                    'role' => 'admin'
                ]
            ], 200);
        });
    }
}
