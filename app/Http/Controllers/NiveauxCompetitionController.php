<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NiveauxCompetition;
use Illuminate\Routing\Controller;

class NiveauxCompetitionController extends Controller
{
    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($activeType === 'Federation') {
            $niveaux = NiveauxCompetition::select('id', 'nom')->get();
        } else {
            $niveaux = NiveauxCompetition::where('nom', 'Regionale')
                ->select('id', 'nom')
                ->get();
        }

        return response()->json($niveaux);
    }
}
