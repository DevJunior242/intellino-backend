<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Role;
use App\Models\League;
use App\Models\Student;
use App\Models\LeagueUser;
use App\Models\StudentGrade;
use Illuminate\Http\Request;
use App\Models\ActivationKey;
use App\Models\ClubNonInscrit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeagueReq;

class LeagueController extends Controller
{

    public function index()
    {
        //recuperer toutes les ligues
        $leagues = League::with(['clubs.affiliationPayments'])
            ->select('id', 'name', 'city', 'phone', 'logo')
            ->get()
            ->map(function ($league) {
                $league->logo = $league->logo ? url('storage/' . $league->logo) : null;
                return $league;
            });
        return response()->json($leagues);
    }

    public function getLeaguesForAdmin()
    {
        // Récupère uniquement l'id et le nom pour que la requête soit ultra légère
        $leagues = League::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'leagues' => $leagues
        ]);
    }
    public function getLeaguePublicInfo($leagueId)
    {
        $league = League::findOrFail($leagueId);
        $league->logo = $league->logo ? url('storage/' . $league->logo) : null;
        return response()->json($league);
    }
    public function store(StoreLeagueReq $request)
    {
        $user = auth()->user();
        try {
            return DB::transaction(function () use ($request, $user) {

                // 1. VÉRIFICATION ET VERROUILLAGE DE LA CLÉ LIGUE
                // Optionnelle : sans clé, la ligue démarre en essai (voir
                // ResolvesTrialStatus) — désactivée automatiquement si aucune
                // clé n'est jamais consommée avant la fin du délai configuré.
                $key = null;
                if ($request->filled('activation_key')) {
                    $key = ActivationKey::where('key_code', $request->activation_key)
                        ->where('is_used', false)
                        ->where('type', 'league')
                        ->lockForUpdate()
                        ->first();

                    if (!$key) {
                        return response()->json([
                            'success' => false,
                            'message' => 'La clé d\'activation est invalide ou a déjà été utilisée.',
                        ], 422);
                    }
                }

                $adminRoleName = Role::where('name', 'admin')->first();
                if (!$adminRoleName) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le role n\'existe pas',
                    ], 422);
                }

                // 2. SÉCURISATION DES DONNÉES DE LA LIGUE
                // On récupère uniquement les données validées de la ligue en ignorant la clé d'activation
                $leagueData = collect($request->validated())->except(['activation_key'])->toArray();

                $file = $request->logo;
                if ($file) {
                    $ext = $file->getClientOriginalExtension();
                    $fileName = uniqid() . '.' . $ext;
                    $path = $file->storeAs('logos', $fileName, 'public');
                }

                // Utilisation des données nettoyées
                $league = League::create([
                    ...$leagueData,
                    'logo' => isset($path) ? $path : null,
                ]);

                // 3. CONSOMMATION DE LA CLÉ (si une clé a été fournie)
                if ($key) {
                    $key->update([
                        'is_used' => true,
                        'used_at' => now(),
                        'used_by_user_id' => $user->id,
                        'used_by_organisation_id' => $league->id,
                    ]);
                }

                $user->current_league_id = $league->id;
                $user->save();


                LeagueUser::create([
                    'league_id' => $league->id,
                    'user_id' => $user->id,
                    'role_id' => $adminRoleName->id,
                    'mandate_start_at' => $request->mandate_start_at,
                    'mandate_end_at' => $request->mandate_end_at,
                ]);

                $user->load('leagues.roles');

                $leagues = $user->leagues->map(function ($c) {
                    $role = $c->roles->firstWhere('id', $c->pivot->role_id);
                    return [
                        'id'   => $c->id,
                        'name' => $c->name,
                        'role' => $role?->name,
                        'mandate_status' => $c->pivot->mandate_status,
                    ];
                });

                return response()->json([
                    'success'     => true,
                    'user'        => $user,
                    'leagues'     => $leagues,
                    'new_league'  => [
                        'id'   => $league->id,
                        'type' => 'Ligue',
                        'role' => 'admin'
                    ]
                ], 201);
            });
        } catch (\Throwable $th) {
            // 4. NETTOYAGE DU LOGO EN CAS D'ÉCHEC DE LA TRANSACTION
            if (isset($path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du league',
                'error'   => $th->getMessage(),
            ], 422);
        }
    }

    public function addClub($clubId, Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');


        $club = Club::findOrFail($clubId);
        $club->league_id = $activeId;
        $club->save();

        return response()->json(['success' => true, 'message' => 'Le club a bien été ajouté à la ligue']);
    }

    public function myClubs(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        if (!$activeId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être dans une ligue pour accéder à ses clubs',
            ], 422);
        }

        $search = $request->search;
        $status = $request->status;

        $clubs = Club::where('league_id', $activeId)
            ->with(['users', 'affiliationPayments', 'country:id,name'])
            ->withCount('licences')
            ->when($search, fn($q) => $q->where('name', 'like', "%$search%"))
            ->when($status, fn($q) => $q->whereHas(
                'affiliationPayments',
                fn($sq) =>
                $sq->where('status', $status)
            ))
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
