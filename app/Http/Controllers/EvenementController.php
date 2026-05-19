<?php

namespace App\Http\Controllers;

use App\Models\Saison;
use App\Models\Evenement;
use App\Models\Competition;
use Illuminate\Http\Request;
use App\Models\ArbitreCompetition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvenementRequest;
use App\Services\ArbitreVerificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EvenementController extends Controller
{

    use AuthorizesRequests;
    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $saisonActive =  Saison::where('active', true)->first();

        if (!$saisonActive) {
            return response()->json(['message' => 'Aucune saison active'], 422);
        }

        if (!$activeId) {
            return response()->json(['message' => 'Aucune organisation active trouvée'], 422);
        }

        $evenements = Evenement::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->where('saison_id', $saisonActive->id)
            ->with([
                'competitions' => function ($query) {
                    $query->with(['niveau:id,nom', 'category:id,nom,sexe', 'discipline:id,nom'])
                        ->withCount('inscriptions')
                        ->orderBy('heure_debut_prevu', 'asc');
                }
            ])
            ->withCount('competitions')
            ->orderBy('date_debut', 'desc')
            ->get();
        return response()->json($evenements);
    }

    public function getEventActive(Request $request)
    {

        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $saisonActive =  Saison::where('active', true)->first();

        if (!$saisonActive) {
            return response()->json(['message' => 'Aucune saison active trouvée'], 422);
        }

        if (!$activeId) {
            return response()->json(['message' => 'Aucune organisation active trouvée'], 422);
        }


        $evenement = Evenement::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->where('saison_id', $saisonActive->id)
            ->with(['competitions' => function ($query) {
                $query->with(['niveau:id,nom', 'category:id,nom,sexe', 'discipline:id,nom'])
                    ->withCount('inscriptions')
                    ->orderBy('heure_debut_prevu', 'asc');
            }])
            ->where('status', Evenement::STATUT_EN_COURS)
            ->latest()
            ->first();

        if (!$evenement) {
            return response()->json([
                'message' => 'Aucun événement actif trouvé',
                'data'    => null
            ], 422);
        }
        return response()->json($evenement);
    }
    public function store(StoreEvenementRequest $request)
    {
        $saisonActive =  Saison::where('active', true)->first();
        if (!$saisonActive) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez définir une saison pour créer un événement',
            ], 422);
        }
        try {
            $result = DB::transaction(function () use ($request, $saisonActive) {
                $validated = $request->validated();

                // 1. Créer l'événement
                $evenement = Evenement::create([
                    'nom'                => $validated['nom'],
                    'lieu'               => $validated['lieu'],
                    'date_debut'         => $validated['date_debut'],
                    'date_fin'           => $validated['date_fin'],
                    'organisateur_id'    => $validated['organisateur_id'],
                    'organisateur_type'  => $validated['organisateur_type'],
                    'status'             => Evenement::STATUT_EN_ATTENTE,
                    'saison_id' => $saisonActive->id,
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
                        'status'              => Competition::STATUT_ATTENTE,
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
        // $this->authorize('open', Evenement::class);
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
        // $this->authorize('close', Evenement::class);
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
