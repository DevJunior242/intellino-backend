<?php

namespace App\Http\Controllers;

use App\Models\OrdrePassage;
use Illuminate\Http\Request;

use App\Models\ConfigNotation;
use App\Models\RotationArbitre;
use App\Services\KataNotationService;
use App\Http\Controllers\Controller;

class KataNotationController extends Controller
{

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ordre_passage_id' => 'required|exists:ordre_passages,id',
            'valeur'           => 'required|numeric|min:0|max:10',
        ]);

        $service = app(KataNotationService::class);

        // 1. Récupérer le passage pour savoir sur quelle config on est
        $passage = OrdrePassage::findOrFail($validated['ordre_passage_id']);

        // 2. Vérifier que l'arbitre est bien ASSIGNÉ et ACTIF sur ce tatami (config)
        $rotation = $service->trouverRotationActive($passage->config_notation_id, auth()->id());

        if (!$rotation) {
            return response()->json(['message' => 'Vous n\'êtes pas autorisé à noter sur ce plateau'], 403);
        }

        // Vérifier si l'ordre de passage est bien en cours
        if ($passage->status !== OrdrePassage::STATUS_STARTED) {
            return response()->json(['message' => 'Cet ordre n\'est pas en cours'], 422);
        }

        // Vérifier que l'arbitre n'a pas déjà noté
        if ($service->aDejaNote($passage->id, $rotation->id)) {
            return response()->json(['message' => 'Vous avez déjà noté cet ordre'], 422);
        }

        // 3. Enregistrer la note
        $note = $service->enregistrerNote($passage, $rotation, $validated['valeur']);

        return response()->json(['success' => true, 'note' => $note]);
    }

    // Saisie centralisée
    public function storeCentralise(Request $request)
    {
        $validated = $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
            'numero_juge'    => 'required|integer|min:1',
            'valeur'         => 'required|numeric|min:0|max:10',
            'config_id'      => 'required|exists:config_notations,id',
        ]);

        $config = ConfigNotation::find($validated['config_id']);

        // Vérifier mode centralisé
        if (!$config->estModeCentralise()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette route est réservée au mode centralisé',
            ], 422);
        }

        // Trouver l'arbitre actif sur ce poste via rotation
        $rotation = RotationArbitre::where('competition_id', $config->competition_id)
            ->where('poste', $validated['numero_juge'])
            ->where('actif', true)
            ->first();

        if (!$rotation) {
            return response()->json([
                'success' => false,
                'message' => "Aucun arbitre actif sur le poste {$validated['numero_juge']}",
            ], 422);
        }

        $note = app(KataNotationService::class)->enregistrerNoteCentralisee(
            $validated['inscription_id'],
            $rotation->arbitre_competition_id,
            $validated['valeur']
        );

        return response()->json([
            'success' => true,
            'message' => "Note Juge {$validated['numero_juge']} enregistrée",
            'data'    => $note,
        ]);
    }

    // Notes et score d'une inscription (un passage)
    public function notesInscription(OrdrePassage $ordrePassage)
    {
        $resultat = app(KataNotationService::class)->resultatPassage($ordrePassage);

        return response()->json([
            'success' => true,
            'data'    => $resultat['notes'],
            'total'   => $resultat['total'],
            'attendu' => $resultat['attendu'],
            'score'   => $resultat['score'],
            'termine' => $resultat['termine'],
            'message' => $resultat['message'],
        ]);
    }
}
