<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Role;
use App\Models\League;
use App\Models\Student;
use App\Models\StudentGrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeagueReq;

class LeagueController extends Controller
{

    public function index()
    {
        //recuperer toutes les ligues
        $leagues = League::select('id', 'name', 'logo')->get();
        return response()->json($leagues);
    }
    // public function index()
    // {
    //     //return les clubs de la ligue
    //     $user = auth()->user();
    //     $leagueId = $user->current_league_id;
    //     if (!$leagueId) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Vous devez être dans une ligue pour accéder à ses clubs',
    //         ], 400);
    //     }
    //     $league = League::findOrFail($leagueId);
    //     return response()->json($league->clubs);
    // }


    public function store(StoreLeagueReq $request)
    {
        $user = auth()->user();
        try {
            return DB::transaction(function () use ($request, $user) {

                $adminRoleName = Role::where('name', 'admin_league')->first();
                //verifier si role existe
                if (!$adminRoleName) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le role  n\'existe pas',
                    ], 400);
                }

                $file = $request->logo;
                if ($file) {
                    $ext = $file->getClientOriginalExtension();
                    $fileName = uniqid() . '.' . $ext;
                    $path = $file->storeAs('logos', $fileName, 'public');
                }
                $league = League::create([
                    ...$request->validated(),
                    'logo' => isset($path) ? $path : null,
                ]);
                $user->current_league_id = $league->id;
                $user->save();

                $user->leagues()->attach($league->id, ['role_id' => $adminRoleName->id]);
                $user->load('leagues.roles');

                $memberships = $user->leagues->map(function ($c) {
                    $role = $c->roles->firstWhere('id', $c->pivot->role_id);
                    return [
                        'league_id'   => $c->id,
                        'league_name' => $c->name,
                        'role_name' => $role?->name,
                    ];
                });


                return response()->json([
                    'success'     => true,
                    'user'        => $user,
                    'memberships' => $memberships,
                    'new_league'    => [
                        'id'   => $league->id,
                        'role' => 'admin_league'
                    ]
                ], 201);
            });
        } catch (\Throwable $th) {
            //throw $th;
            Log::error('erreur', ['erreur' => $th->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du league',
            ], 400);
        }
    }


    public function addClub($clubId)
    {
        $user = auth()->user();
        $leagueId = $user->current_league_id;
        Log::info('clubId', ['clubId' => $clubId]);
        Log::info('leagueId', ['leagueId' => $leagueId]);

        $club = Club::findOrFail($clubId);
        $club->league_id = $leagueId;
        $club->save();

        return response()->json(['success' => true, 'message' => 'Le club a bien été ajouté à la ligue']);
    }

    public function myClubs()
    {
        $user = auth()->user();
        $leagueId = $user->current_league_id;
        if (!$leagueId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être dans une ligue pour accéder à ses clubs',
            ], 400);
        }
        $clubs = Club::where('league_id', $leagueId)
            ->with('users')
            ->latest()
            ->paginate(8);
        $clubs->getCollection()->transform(function ($club) {
            $club->logo = $club->logo ? url('storage/' . $club->logo) : null;
            return $club;
        });
        return response()->json($clubs);
    }

    public function getLeagueStudents(Request $request)
    {
        $user = auth()->user();

        $clubId = $request->query('club_id');
        $students = StudentGrade::with('student:id,fullname', 'currentGrade:id,name,description')
            ->where('club_id', $clubId)
            ->get()
            ->map(function ($student) {
                $student->photo = $student->photo ? url('storage/' . $student->photo) : null;
                return $student;
            });

        return response()->json($students);
    }
}
