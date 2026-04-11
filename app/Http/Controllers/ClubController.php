<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClubStoreRequest;

class ClubController extends Controller
{

   public function index()
   {
      //listes des clubs
      $clubs = Club::select('id', 'name', 'logo', 'city', 'country', 'discipline_id')
         ->with(['discipline:id,name'])
         ->withCount('users')
         ->latest()
         ->take(10)
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
      $clubs = Club::whereNull('league_id')
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
      $clubs = Club::select('id', 'name')
         ->get();

      return response()->json($clubs);
   }
   public function store(ClubStoreRequest $request)
   {
      $user = auth()->user();
      try {
         return DB::transaction(function () use ($request, $user) {

            $adminRoleName = Role::where('name', 'admin_club')->first();
            //verifier si role existe
            if (!$adminRoleName) {
               return response()->json([
                  'success' => false,
                  'message' => 'Le role admin_club n\'existe pas',
               ], 400);
            }

            $file = $request->logo;
            if ($file) {
               $ext = $file->getClientOriginalExtension();
               $fileName = uniqid() . '.' . $ext;
               $path = $file->storeAs('logos', $fileName, 'public');
            }
            $club = Club::create([
               ...$request->validated(),
               'logo' => isset($path) ? $path : null,
            ]);
            $user->update([
               'current_club_id' => $club->id,
            ]);

            $user->clubs()->attach($club->id, ['role_id' => $adminRoleName->id]);
            $user->load('clubs.roles');

            $memberships = $user->clubs->map(function ($c) {
               $role = $c->roles->firstWhere('id', $c->pivot->role_id);
               return [
                  'id'   => $c->id,
                  'name' => $c->name,
                  'role' => $role?->name,

               ];
            });


            return response()->json([
               'success'     => true,
               'user'        => $user,
               'memberships' => $memberships,
               'new_club'    => [
                  'id'   => $club->id,
                  'role' => 'admin_club'
               ]
            ], 201);
         });
      } catch (\Throwable $th) {
         //throw $th;
         Log::error('erreur', ['erreur' => $th->getMessage()]);
         return response()->json([
            'success' => false,
            'message' => 'Une erreur est survenue lors de la création du club',
         ], 400);
      }
   }
}
