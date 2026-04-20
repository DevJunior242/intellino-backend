<?php

namespace App\Http\Controllers;

use App\Models\Evenement;
use Illuminate\Http\Request;
use App\Models\ArbitreCompetition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvenementRequest;
use App\Services\ArbitreVerificationService;

class EvenementController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Détermination de l'organisateur (Ligue ou Fédération)
        $activeOrgId = $user->current_league_id ?? $user->current_federation_id;


        $activeOrgType = $user->current_league_id ? 'Ligue' : 'Federation';

        if (!$activeOrgId) {
            return response()->json(['message' => 'Aucune organisation active trouvée'], 404);
        }

        $evenements = Evenement::where('organisateur_id', $activeOrgId)
            ->where('organisateur_type', $activeOrgType)
            ->with([
                'competitions' => function ($query) {
                    $query->with(['niveau', 'category', 'discipline'])
                        ->withCount('inscriptions')
                        ->orderBy('heure_debut_prevu', 'asc');
                }
            ])
            ->withCount('competitions')
            ->orderBy('date_debut', 'desc')
            ->get();

        return response()->json($evenements);
    }

    public function getEventActive()
    {
        $user = auth()->user();

        // Détermination de l'organisateur (Ligue ou Fédération)
        $activeOrgId = $user->current_league_id ?? $user->current_federation_id;


        $activeOrgType = $user->current_league_id ? 'Ligue' : 'Federation';

        if (!$activeOrgId) {
            return response()->json(['message' => 'Aucune organisation active trouvée'], 404);
        }

        $evenement = Evenement::where('organisateur_id', $activeOrgId)
            ->where('organisateur_type', $activeOrgType)
            ->with(['competitions' => function ($query) {
                $query->with(['niveau', 'category', 'discipline'])
                    ->withCount('inscriptions')
                    ->orderBy('heure_debut_prevu', 'asc');
            }])
            ->withCount('competitions')
            ->where('status', Evenement::STATUT_EN_COURS)
            ->latest()
            ->first();

        if (!$evenement) {
            return response()->json([
                'message' => 'Aucun événement actif trouvé',
                'data'    => null
            ], 200);
        }

        return response()->json($evenement);
    }
    public function store(StoreEvenementRequest $request)
    {
        try {
            $result = DB::transaction(function () use ($request) {
                $validated = $request->validated();

                // 1. Créer l'événement
                $evenement = Evenement::create([
                    'nom'                => $validated['nom'],
                    'lieu'               => $validated['lieu'],
                    'date_debut'         => $validated['date_debut'],
                    'date_fin'           => $validated['date_fin'],
                    'organisateur_id'    => $validated['organisateur_id'],
                    'organisateur_type'  => $validated['organisateur_type'],
                    'status'             => 0,
                ]);

                // 2. Créer les épreuves
                foreach ($validated['epreuves'] as $item) {
                    $evenement->competitions()->create([
                        'evenement_id'        => $evenement->id,
                        'category_id'         => $item['category_id'],
                        'disciplineleague_id' => $item['disciplineleague_id'],
                        'niveau_id'           => $item['niveau_id'],
                        'heure_debut_prevu'   => $item['heure_debut_prevu'],
                        'heure_fin_prevue'    => $item['heure_fin_prevue'],
                        'status'              => 0,
                    ]);
                }

                return $evenement->load('competitions.category', 'competitions.discipline');
            });

            return response()->json([
                'success' => true,
                'message' => 'Événement créé avec succès',
                'data'    => $result,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    // Ouvrir les inscriptions
    public function ouvrir(Evenement $evenement)
    {
        $evenement->update([
            'status' => 1,

        ]);

        return response()->json([
            'event' => $evenement,
            'message' => 'Les inscriptions sont maintenant ouvertes !'
        ]);
    }

    // Clôturer manuellement
    public function cloturer(Evenement $evenement)
    {
        $evenement->update(['status' => 2]);

        return response()->json(['message' => 'Les inscriptions ont été clôturées.']);
    }
    public function arbitrer(
        Evenement $evenement,
        ArbitreVerificationService $verif
    ) {
        Log::info('isArbitre', ['isArbitre' => $verif->estArbitre($evenement, auth()->id())]);
        if (!$verif->estArbitre($evenement, auth()->id())) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas arbitre dans cette organisation',
            ], 403);
        }

        $arbitre = ArbitreCompetition::firstOrCreate([
            'user_id'        => auth()->id(),
            'evenement_id'   => $evenement->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inscription confirmée',
            'data'    => $arbitre,
        ]);
    }
}
