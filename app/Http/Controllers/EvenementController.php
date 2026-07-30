<?php

namespace App\Http\Controllers;

use App\Models\Evenement;
use App\Models\Competition;
use Illuminate\Http\Request;
use App\Models\ArbitreCompetition;
use App\Models\NiveauxCompetition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvenementRequest;
use App\Services\ArbitreVerificationService;
use App\Http\Requests\UpdateEvenementRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Controllers\Concerns\ResolvesActiveSaison;

class EvenementController extends Controller
{

    use AuthorizesRequests;
    use ResolvesActiveSaison;

    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $saisonActive = $this->saisonActivePour($activeId, $activeType);

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
                    $query->with(['niveau:id,nom', 'category:id,nom,sexe', 'subDiscipline:id,nom'])
                        ->withCount('inscriptions')
                        ->orderBy('heure_debut_prevu', 'asc');
                }
            ])
            ->withCount('competitions')
            ->withCount(['arbitreCompetitions as arbitre_inscrit_count' => function ($query) {
                $query->where('user_id', auth()->id());
            }])
            ->orderBy('date_debut', 'desc')
            ->latest()
            ->paginate(2);
        return response()->json($evenements);
    }

    public function getEventActive(Request $request)
    {

        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $saisonActive = $this->saisonActivePour($activeId, $activeType);

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
                $query->with(['niveau:id,nom', 'category:id,nom,sexe', 'subDiscipline:id,nom'])
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
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier l\'organisateur connecté.'
            ], 403);
        }

        $saisonActive = $this->saisonActivePour($activeId, $activeType);

        if (!$saisonActive) {
            return response()->json(['message' => 'Aucune saison active trouvée'], 422);
        }

        try {
            $result = DB::transaction(function () use ($request, $saisonActive, $activeId, $activeType) {
                $validated = $request->validated();

                // 1. Créer l'événement
                $evenement = Evenement::create([
                    'nom'                => $validated['nom'],
                    'lieu'               => $validated['lieu'],
                    'date_debut'         => $validated['date_debut'],
                    'date_fin'           => $validated['date_fin'],
                    'organisateur_id'    => $activeId,
                    'organisateur_type'  => $activeType,
                    'status'             => Evenement::STATUT_EN_ATTENTE,
                    'saison_id' => $saisonActive->id,
                ]);

                // 2. Créer les épreuves
                foreach ($validated['epreuves'] as $item) {
                    $niveau = NiveauxCompetition::findOrFail($item['niveau_id']);

                    if (
                        $activeType === 'Ligue' &&
                        strtolower($niveau->nom) === 'nationale'
                    ) {
                        throw ValidationException::withMessages([
                            'epreuves' => [
                                'Une ligue ne peut pas organiser une compétition nationale.'
                            ]
                        ]);
                    }
                    $evenement->competitions()->create([
                        'evenement_id'        => $evenement->id,
                        'category_id'         => $item['category_id'],
                        'sub_discipline_id' => $item['sub_discipline_id'],
                        'niveau_id'           => $item['niveau_id'],
                        'heure_debut_prevu'   => $item['heure_debut_prevu'],
                        'heure_fin_prevue'    => $item['heure_fin_prevue'],
                        'status'              => Competition::STATUT_ATTENTE,
                    ]);
                }

                return $evenement->load('competitions.category', 'competitions.subDiscipline');
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
    //update 
    public function update(UpdateEvenementRequest $request, Evenement $evenement)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        // On vérifie que l'événement appartient bien à l'organisateur connecté
        if (
            $evenement->organisateur_id !== $activeId ||
            $evenement->organisateur_type !== $activeType
        ) {
            return response()->json([
                'success' => false,
                'message' => "Vous n'êtes pas autorisé à modifier cet événement.",
            ], 403);
        }

        try {
            $result = DB::transaction(function () use ($request, $evenement, $activeType) {
                $validated = $request->validated();

                // 1. Mettre à jour les infos de l'événement
                $evenement->update([
                    'nom'        => $validated['nom'],
                    'lieu'       => $validated['lieu'],
                    'date_debut' => $validated['date_debut'],
                    'date_fin'   => $validated['date_fin'],
                ]);

                // 2. Sync des épreuves : update si id présent, create sinon,
                //    delete celles absentes de la requête.
                $epreuvesEnvoyees = collect($validated['epreuves']);
                $idsEnvoyes = $epreuvesEnvoyees->pluck('id')->filter()->all();

                // 2a. Supprimer les épreuves retirées du formulaire.
                //     Cascade delete déjà géré par la BDD (FK on delete cascade),
                //     donc les inscriptions liées partent automatiquement.
                //     On avertit simplement si des inscriptions existaient.
                $epreuvesASupprimer = $evenement->competitions()
                    ->whereNotIn('id', $idsEnvoyes)
                    ->withCount('inscriptions')
                    ->get();

                foreach ($epreuvesASupprimer as $epreuve) {
                    if ($epreuve->inscriptions_count > 0) {
                        Log::warning("Suppression d'une épreuve avec inscriptions", [
                            'competition_id'     => $epreuve->id,
                            'evenement_id'        => $evenement->id,
                            'inscriptions_count'  => $epreuve->inscriptions_count,
                        ]);
                    }
                }

                $evenement->competitions()
                    ->whereNotIn('id', $idsEnvoyes)
                    ->delete();

                // 2b. Update / create pour chaque épreuve envoyée
                foreach ($epreuvesEnvoyees as $item) {
                    $niveau = NiveauxCompetition::findOrFail($item['niveau_id']);

                    if (
                        $activeType === 'Ligue' &&
                        strtolower($niveau->nom) === 'nationale'
                    ) {
                        throw ValidationException::withMessages([
                            'epreuves' => [
                                'Une ligue ne peut pas organiser une compétition nationale.',
                            ],
                        ]);
                    }

                    $payload = [
                        'category_id'         => $item['category_id'],
                        'sub_discipline_id' => $item['sub_discipline_id'],
                        'niveau_id'           => $item['niveau_id'],
                        'heure_debut_prevu'   => $item['heure_debut_prevu'],
                        'heure_fin_prevue'    => $item['heure_fin_prevue'],
                    ];

                    if (!empty($item['id'])) {
                        // Épreuve existante : on met à jour sans toucher au status
                        // (le status est piloté par handleEpreuveStatusChange,
                        // pas par le formulaire d'édition).
                        $evenement->competitions()
                            ->where('id', $item['id'])
                            ->update($payload);
                    } else {
                        // Nouvelle épreuve ajoutée pendant l'édition
                        $evenement->competitions()->create(array_merge($payload, [
                            'evenement_id' => $evenement->id,
                            'status'       => Competition::STATUT_ATTENTE,
                        ]));
                    }
                }

                return $evenement->load('competitions.category', 'competitions.subDiscipline');
            });

            return response()->json([
                'success' => true,
                'message' => 'Événement mis à jour avec succès',
                'data'    => $result,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    //destroy
    public function destroy(Evenement $evenement)
    {
        //$this->authorize('delete', Evenement::class);
        $evenement->delete();
        return response()->json([
            'success' => true,
            'message' => 'Événement supprimé avec succès',
        ]);
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
