<?php

namespace App\Http\Controllers;

use App\Models\SubDiscipline;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\ResolvesActiveSaison;

class DisciplineConfigController extends Controller
{
    use ResolvesActiveSaison;

    /**
     * Une Ligue affiliée à une Fédération hérite des disciplines de sa
     * Fédération. Une Ligue indépendante (pas de federation_id) gère les
     * siennes — même logique que la saison, voir ResolvesActiveSaison.
     */
    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $organisateur = $this->organisateurEffectifSaison($activeId, $activeType);

        if (!$organisateur) {
            return response()->json([]);
        }

        $disciplines = SubDiscipline::where('organisateur_id', $organisateur['id'])
            ->where('organisateur_type', $organisateur['type'])
            ->select('id', 'nom')
            ->get();

        return response()->json($disciplines);
    }
}
