<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Stage;
use App\Models\League;
use App\Models\Saison;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesActiveSaison;

class StageController extends Controller
{
    use ResolvesActiveSaison;

    public function index(Request $request)
    {
        // 1. Récupération sécurisée des attributs du middleware
        $activeId   = $request->attributes->get('organisateur_id');
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

        // 2. Validation des filtres optionnels (query params, pour le futur composant)
        $filters = $request->validate([
            'type'      => 'nullable|string',
            'start_at'  => 'nullable|date',
            'end_at'    => 'nullable|date',
            'search'    => 'nullable|string|max:255',
            'saison_id' => 'nullable|integer|exists:saisons,id',
        ]);

        // 3. Requête de base : isolation stricte par organisateur connecté
        $query = Stage::query()
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->where('saison_id', $filters['saison_id'] ?? $saisonActive->id);

        // 4. Application des filtres optionnels
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['start_at'])) {
            $query->whereDate('start_at', '>=', $filters['start_at']);
        }

        if (!empty($filters['end_at'])) {
            $query->whereDate('end_at', '<=', $filters['end_at']);
        }

        if (!empty($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }

        // 5. Résultat
        $stages = $query->latest('start_at')->paginate(8);

        return response()->json([
            'success' => true,
            'data'    => $stages,
            'meta'    => [
                'organisateur_id'   => $activeId,
                'organisateur_type' => $activeType,
                'saison_id'         => $filters['saison_id'] ?? $saisonActive->id,
            ],
        ]);
    }
    public function store(Request $request)
    {
        // 1. On valide les données de base (le niveau n'est plus requis ici puisqu'on le gère par le ternaire)
        $validated = $request->validate([
            'title'    => 'required|string|max:255',
            'type'     => 'required|string',
            'price'    => 'required|numeric|min:0',
            'start_at' => 'required|date|after_or_equal:today',
            'end_at'   => 'nullable|date|after:start_at',
        ], [
            'start_at.after' => 'La date de début doit être postérieure à la date du jour',
            'end_at.after' => 'La date de fin doit être postérieure à la date de début',
            'end_at.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début',
            'title.required' => 'Le titre est obligatoire',
            'type.required' => 'Le type est obligatoire',
            'price.required' => 'Le prix est obligatoire',
            'start_at.required' => 'La date de début est obligatoire',

        ]);

        // 2. Récupération sécurisée des attributs du middleware
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

        // 3. Sécurisation et isolation dans une transaction de base de données
        return DB::transaction(function () use ($validated, $activeId, $activeType, $saisonActive) {

            $level = (str_contains(strtolower($activeType), 'ligue')) ? 'Ligue' : 'Federal';

            $stage = Stage::create([
                'title'             => $validated['title'],
                'type'              => $validated['type'],
                'price'             => $validated['price'],
                'level'             => $level,
                'start_at'          => $validated['start_at'],
                'end_at'            => $validated['end_at'],
                'organisateur_id'   => $activeId,
                'organisateur_type' => $activeType,
                'saison_id' => $saisonActive->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stage créé avec succès !',
                'data'    => $stage
            ], 201);
        });
    }
    public function update(Request $request, Stage $stage)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier l\'organisateur connecté.'
            ], 403);
        }

        if ($stage->organisateur_id !== $activeId || $stage->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à modifier ce stage.'
            ], 403);
        }

        $validated = $request->validate([
            'title'    => 'required|string|max:255',
            'type'     => 'required|string',
            'start_at' => 'required|date',
            'end_at'   => 'nullable|date|after_or_equal:start_at',
        ]);

        $stage->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Stage modifié avec succès !',
            'data'    => $stage,
        ]);
    }

    //destroy
    public function destroy(Request $request, Stage $stage)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier l\'organisateur connecté.'
            ], 403);
        }

        // Isolation : on ne peut supprimer que ses propres stages
        if ($stage->organisateur_id !== $activeId || $stage->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à supprimer ce stage.'
            ], 403);
        }

        $stage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stage supprimé avec succès !'
        ], 200);
    }

    public function stagesDeMaLigue(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier l\'organisateur connecté.'
            ], 403);
        }

        // Cette fonctionnalité ne concerne que les clubs (pas les ligues elles-mêmes)
        if ($activeType !== 'Club') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un club peut consulter les stages de sa ligue.'
            ], 403);
        }

        $club = Club::find($activeId);

        if (!$club) {
            return response()->json(['message' => 'Club introuvable.'], 404);
        }

        if (!$club->league_id) {
            return response()->json([
                'success' => false,
                'message' => 'Ce club n\'est rattaché à aucune ligue.'
            ], 422);
        }

        $saisonActive = $this->saisonActivePour($club->league_id, 'Ligue');

        if (!$saisonActive) {
            return response()->json(['message' => 'Aucune saison active trouvée'], 422);
        }

        // Vérification explicite : la ligue cible existe bien
        $league = League::find($club->league_id);

        if (!$league) {
            return response()->json([
                'success' => false,
                'message' => 'La ligue rattachée à ce club est introuvable.'
            ], 404);
        }

        // Filtres optionnels, réutilisables  
        $filters = $request->validate([
            'type'   => 'nullable|string',
            'search' => 'nullable|string|max:255',
        ]);

        $query = Stage::query()
            ->where('organisateur_type', 'Ligue')
            ->where('organisateur_id', $league->id)
            ->where('saison_id', $saisonActive->id);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }

        $stages = $query->latest('start_at')->paginate(8);

        return response()->json([
            'success' => true,
            'data'    => $stages,
            'meta'    => [
                'league_id'   => $league->id,
                'league_name' => $league->name ?? null,
            ],
        ]);
    }
}
