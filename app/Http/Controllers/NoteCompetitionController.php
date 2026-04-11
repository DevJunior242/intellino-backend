<?php

namespace App\Http\Controllers;

use App\Models\ScoreKata;
use Illuminate\Http\Request;

class NoteCompetitionController extends Controller
{
    //

    // Dans ton contrôleur Laravel
    public function storeScore(Request $request)
    {
        $notes = $request->notes;

        // 1. On trie les notes
        sort($notes);

        // 2. On calcule le score final (WKF 7 juges)
        // On enlève les 2 plus petites et les 2 plus grandes
        $notesConservees = array_slice($notes, 2, 3);
        $scoreFinal = array_sum($notesConservees);

        // 3. On enregistre tout
        $score = ScoreKata::create([
            'inscription_id' => $request->inscription_id,
            'notes' => $notes,
            'score_final' => $scoreFinal,
            'tour' => $request->tour
        ]);

        return response()->json($score);
    }
}
