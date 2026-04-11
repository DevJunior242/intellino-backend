<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Inscription;
use App\Models\OrdrePassage;
use Illuminate\Http\Request;

use App\Models\ConfigNotation;
use App\Models\RotationArbitre;
use App\Models\ArbitreCompetition;
use App\Http\Controllers\Controller;

class NoteController extends Controller
{

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ordre_passage_id' => 'required|exists:ordre_passages,id',
            'valeur'           => 'required|numeric|min:0|max:10',
        ]);

        // 1. Récupérer le passage pour savoir sur quelle config on est
        $passage = OrdrePassage::findOrFail($validated['ordre_passage_id']);

        // 2. Vérifier que l'arbitre est bien ASSIGNÉ et ACTIF sur ce tatami (config)
        $rotation = RotationArbitre::where('config_notation_id', $passage->config_notation_id)
            ->whereHas('arbitreCompetition', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->where('actif', true)
            ->first();

        if (!$rotation) {
            return response()->json(['message' => 'Vous n\'êtes pas autorisé à noter sur ce plateau'], 403);
        }
        //verifier si ordre est en_cours 
        $ordre = OrdrePassage::find($validated['ordre_passage_id']);
        if ($ordre->statut !== 'en_cours') {
            return response()->json(['message' => 'Cet ordre n\'est pas en cours'], 422);
        }
        // Vérifier que l'arbitre n'a pas déjà noté
        $dejaNote = Note::where('ordre_passage_id', $validated['ordre_passage_id'])
            ->where('rotation_arbitre_id', $rotation->id)
            ->exists();
        if ($dejaNote) {
            return response()->json(['message' => 'Vous avez déjà noté cet ordre'], 422);
        }

        // 3. Enregistrer la note
        $note = Note::updateOrCreate(
            [
                'ordre_passage_id' => $passage->id,
                'rotation_arbitre_id' => $rotation->id,
            ],
            [
                'valeur' => $validated['valeur'],
                'note_a' => now(),
            ]
        );

        return response()->json(['success' => true, 'note' => $note]);
    }

    // NoteController — saisie centralisée
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

        $note = Note::updateOrCreate(
            [
                'inscription_id'         => $validated['inscription_id'],
                'arbitre_competition_id' => $rotation->arbitre_competition_id,
            ],
            ['valeur' => $validated['valeur']]
        );

        return response()->json([
            'success' => true,
            'message' => "Note Juge {$validated['numero_juge']} enregistrée",
            'data'    => $note,
        ]);
    }
    // Notes d'une inscription
    public function notesInscription(OrdrePassage $ordrePassage)
    {
        $notes = Note::where('ordre_passage_id', $ordrePassage->id)
            ->with([
                'rotationArbitre.arbitreCompetition.user:id,fullname',
                'ordrePassage.inscription.athlete:id,fullname'
            ])
            ->get();

        // 2. Transformer les données pour le frontend
        $dataNotes = $notes->map(fn($note) => [
            'id'      => $note->id,
            'valeur'  => (float) $note->valeur,
            'poste'   => $note->rotationArbitre?->poste ?? "Inconnu",
            'arbitre' => $note->rotationArbitre?->arbitreCompetition?->user?->fullname ?? "Inconnu",
        ]);

        $config = $ordrePassage->configNotation()
            ->with('nbJugesOption')
            ->first();
        $nbJugesAttendus = $config->nbJugesOption ? (int) $config->nbJugesOption->valeur : 5;
        // 4. Calculer le score
        $score = $this->calculerScore(
            $dataNotes->pluck('valeur')->toArray(),
            $nbJugesAttendus
        );

        // 5. Récupérer le nom de l'athlète via le passage
        $athleteNom = $ordrePassage->inscription?->athlete?->fullname ?? "Athlète inconnu";

        return response()->json([
            'success' => true,
            'data'    => $dataNotes,
            'total'   => $dataNotes->count(),
            'attendu' => $nbJugesAttendus,
            'score'   => $score,
            'termine' => !is_null($score),
            'message' => is_null($score)
                ? "En attente des notes ({$dataNotes->count()}/{$nbJugesAttendus})"
                : "L'athlète {$athleteNom} a obtenu {$score} points",
        ]);
    }

    private function calculerScore(array $valeurs, int $nbJugesAttendus): ?float
    {
        // On ne calcule que si TOUS les juges ont noté
        if (count($valeurs) < $nbJugesAttendus) return null;

        sort($valeurs);


        $nbAEnlever = ($nbJugesAttendus === 7) ? 2 : 1;

        $retenues = array_slice($valeurs, $nbAEnlever, count($valeurs) - ($nbAEnlever * 2));

        return (float) array_sum($retenues);
    }
}
