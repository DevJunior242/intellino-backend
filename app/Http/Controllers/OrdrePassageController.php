<?php

namespace App\Http\Controllers;

use App\Models\Inscription;
use App\Models\OrdrePassage;
use Illuminate\Http\Request;

class OrdrePassageController extends Controller
{

    public function index($configId)
    {

        $orders = OrdrePassage::with(
            [
                'inscription.athlete:id,fullname',
                'inscription.organisateur:id,name',
                'inscription.competition.category:id,nom,sexe',
            ]
        )
            ->withCount('inscription')
            ->where('config_notation_id', $configId)
            ->orderBy('ordre', 'asc')
            ->get();

        return response()->json($orders);
    }

    //nnon assign
    public function getNonAssignees($competitionId)
    {
        // On récupère les IDs des inscriptions déjà présentes dans une file d'attente (ordre_passages)
        $dejaAssigneesIds = OrdrePassage::pluck('inscription_id');

        // On retourne les inscriptions de cette compétition qui NE SONT PAS dans cette liste
        $inscriptions = Inscription::with(['athlete:id,fullname', 'organisateur:id,name'])
            ->where('competition_id', $competitionId)
            ->whereNotIn('id', $dejaAssigneesIds)
            ->get();

        return response()->json($inscriptions);
    }

    // Assigner
    public function assigner(Request $request)
    {
        $configId = $request->config_notation_id;
        $inscriptionId = $request->inscription_id;
        // On calcule le prochain numéro d'ordre pour ce tatami
        $dernierOrdre = OrdrePassage::where('config_notation_id', $configId)->max('ordre') ?? 0;

        return OrdrePassage::updateOrCreate(
            ['inscription_id' => $inscriptionId],
            [
                'config_notation_id' => $configId,
                'ordre' => $dernierOrdre + 1,
                'status' => OrdrePassage::STATUS_NOT_STARTED,
            ]
        );
    }

    // Retirer
    public function retirer($inscriptionId)
    {
        $order = OrdrePassage::where('inscription_id', $inscriptionId)->first();

        if (!$order) {
            return response()->json(['message' => 'Ordre de passage introuvable'], 422);
        }

        $order->delete();
        return response()->json(['message' => 'Retiré avec succès']);
    }
}
