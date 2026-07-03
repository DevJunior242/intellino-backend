<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Role;
use App\Models\ActivationKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClubStoreRequest;

class ClubController extends Controller
{

   public function index()
   {
      //listes des clubs
      $clubs = Club::select(
         'id',
         'name',
         'logo',
         'city',
         'address',
         'discipline_id',
         'country_id'
      )
         ->with(['discipline:id,name', 'country:id,name'])
         ->withCount('users')
         ->latest()
         ->get()
         ->map(function ($club) {
            $club->logo = $club->logo ? url('storage/' . $club->logo) : null;
            return $club;
         });

      return response()->json($clubs);
   }
   //club available
   public function getClubsAvailable()
   {
      $clubs = Club::with(['country:id,name'])
         ->whereNull('league_id')
         ->latest()
         ->paginate(8);
      $clubs->getCollection()->transform(function ($club) {
         $club->logo = $club->logo ? url('storage/' . $club->logo) : null;
         return $club;
      });
      return response()->json([
         'clubs' => $clubs,
      ]);
   }



   public function getClubs()
   {
      $clubs = Club::select('id', 'name', 'logo', 'city', 'address')
         ->get();

      return response()->json($clubs);
   }


   public function store(ClubStoreRequest $request)
   {
      $user = auth()->user();
      try {
         return DB::transaction(function () use ($request, $user) {

            // 1. VERIFICATION ET VERROUILLAGE DE LA CLÉ D'ACTIVATION
            $key = ActivationKey::where('key_code', $request->activation_key)
               ->where('is_used', false)
               ->where('type', 'club')
               ->lockForUpdate()
               ->first();

            if (!$key) {
               return response()->json([
                  'success' => false,
                  'message' => 'La clé d\'activation est invalide ou a déjà été utilisée.',
               ], 422);
            }

            // 2. VERIFICATION DU ROLE
            $adminRoleName = Role::where('name', 'admin')->first();
            if (!$adminRoleName) {
               return response()->json([
                  'success' => false,
                  'message' => 'Le role admin n\'existe pas',
               ], 422);
            }

            // 3. GESTION DU LOGO
            $file = $request->logo;
            if ($file) {
               $ext = $file->getClientOriginalExtension();
               $fileName = uniqid() . '.' . $ext;
               $path = $file->storeAs('logos', $fileName, 'public');
            }

            // 4. CREATION DU CLUB
            $club = Club::create([
               ...$request->validated(),
               'logo' => isset($path) ? $path : null,
            ]);

            // 5. MARQUER LA CLÉ COMME CONSOMMÉE
            $key->update([
               'is_used' => true,
               'used_at' => now(),
               'used_by_user_id' => $user->id,
            ]);

            // 6. LOGIQUE UTILISATEUR & ROLES (Ton code d'origine)
            $user->updateQuietly([
               'current_club_id' => $club->id,
            ]);

            $user->clubs()->attach($club->id, ['role_id' => $adminRoleName->id]);
            $user->load('clubs.roles');

            $clubs = $user->clubs->map(function ($c) {
               $role = $c->roles->firstWhere('id', $c->pivot->role_id);
               return [
                  'id'   => $c->id,
                  'name' => $c->name,
                  'role' => $role?->name,
               ];
            });

            return response()->json([
               'success'  => true,
               'message'  => 'Le club a été créé avec succès',
               'user'     => $user,
               'clubs'    => $clubs,
               'new_club' => [
                  'id'   => $club->id,
                  'type' => 'Club',
                  'role' => 'admin'
               ]
            ], 201);
         });
      } catch (\Throwable $th) {
         return response()->json([
            'success' => false,
            'error'   => $th->getMessage(),
            'message' => 'Une erreur est survenue lors de la création du club',
         ], 422);
      }
   }
   public function count()
   {
      $clubs = Club::count();
      return response()->json($clubs);
   }
}
