<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

class DisciplineConfigController extends Controller
{

    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        // 1. Initialisation des identifiants cibles avec les valeurs actuelles
        $targetFederationId = $activeId;

        // 2. Si c'est une Ligue qui interroge l'API, on remonte à sa Fédération parente
        if ($activeType === 'Ligue') {
            // On va chercher la ligue actuelle pour connaître sa federation_id
            $league = \App\Models\League::find($activeId);

            if ($league) {
                $targetFederationId = $league->federation_id;
            }
        }

        // 3. On filtre toujours sur le type 'Federation' car c'est elle qui gère la charte nationale
        $disciplines = \App\Models\SubDiscipline::where('organisateur_id', $targetFederationId)
            ->where('organisateur_type', 'Federation')
            ->select('id', 'nom')
            ->get();

        return response()->json($disciplines);
    }
}
