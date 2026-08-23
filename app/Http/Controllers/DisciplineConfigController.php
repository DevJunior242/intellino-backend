<?php

namespace App\Http\Controllers;

use App\Models\SubDiscipline;
use Illuminate\Http\Request;

class DisciplineConfigController extends Controller
{
    /**
     * Chaque Ligue (affiliée ou non) et chaque Fédération gère ses propres
     * disciplines de façon indépendante — voir SubDisciplineController::store().
     */
    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([]);
        }

        $disciplines = SubDiscipline::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->select('id', 'nom')
            ->get();

        return response()->json($disciplines);
    }
}
