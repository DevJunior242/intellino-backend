<?php

namespace App\Http\Controllers;

use App\Models\Licence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class LicenceleagueController extends Controller
{
    public function index(Request $request)
    {
        try {
            // 1. Récupération du contexte de la ligue connectée via le middleware
            $leagueId = $request->attributes->get('organisateur_id');

            if (!$leagueId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible d\'identifier la ligue.'
                ], 403);
            }

            // 2. Initialisation de la requête avec les relations nécessaires à ton tableau React
            $query = Licence::query()
                // 1. On fait une jointure pour lier la licence à son club
                ->join('clubs', 'clubs.id', '=', 'licences.club_id')

                // 2. On sécurise le périmètre immédiatement sur la ligue active via la table jointe
                ->where('clubs.league_id', $leagueId)

                // 3. On sélectionne UNIQUEMENT les colonnes de la table licences 
                // Très important pour éviter que les IDs de la table clubs n'écrasent ceux de tes licences !
                ->select('licences.*')

                // 4. On charge proprement toutes les relations pour React (y compris la saison)
                ->with([
                    'student:id,fullname',
                    'club:id,name',
                    'saison:id,libele,active'
                ]);
            // 3. Filtre de recherche par Nom de l'élève ou Numéro de licence (Input du front)
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('numero', 'LIKE', "%{$search}%")
                        ->orWhereHas('student', function ($studentQuery) use ($search) {
                            $studentQuery->where('fullname', 'LIKE', "%{$search}%");
                        });
                });
            }

            // 4. Filtre par Statut (Select du front)
            // Attention : gère le cas où la valeur vaut "0" (En attente) pour ne pas la confondre avec empty
            if ($request->has('status') && $request->status !== null && $request->status !== '') {
                $query->where('status', intval($request->status));
            }

            // 5. Pagination formatée pour le composant TablePagination de MUI
            // Ton React cherche les données dans response.data.data
            $perPage = $request->get('per_page', 10);
            $paginatedLicences = $query->latest()->paginate($perPage);

            return response()->json([
                'success'      => true,
                'data'         => $paginatedLicences->items(), // Liste des licences
                'total'        => $paginatedLicences->total(), // Requis pour count
                'current_page' => $paginatedLicences->currentPage(), // Requis pour page
                'last_page'    => $paginatedLicences->lastPage(),
                'per_page'     => $paginatedLicences->perPage(),
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des licences.'
            ], 500);
        }
    }
}
