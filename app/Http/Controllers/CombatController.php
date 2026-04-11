<?php

namespace App\Http\Controllers;

use App\Models\Combat;
use Illuminate\Http\Request;
use App\Models\ConfigNotation;
use App\Http\Controllers\Controller;

class CombatController extends Controller
{
    // Controller
    public function bracket(ConfigNotation $config)
    {
        $combats = Combat::where('config_notation_id', $config->id)
            ->with([
                'inscriptionAka.athlete',
                'inscriptionAo.athlete',
            ])
            ->orderBy('ordre')
            ->get()
            ->groupBy('etape');

        return response()->json([
            'success' => true,
            'data'    => $combats,
        ]);
    }
}
