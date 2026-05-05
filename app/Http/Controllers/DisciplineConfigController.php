<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Disciplineleague;
use App\Http\Controllers\Controller;

class DisciplineConfigController extends Controller
{

    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $disciplines = Disciplineleague::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->select('id', 'nom')
            ->get();
        return response()->json($disciplines);
    }
}
