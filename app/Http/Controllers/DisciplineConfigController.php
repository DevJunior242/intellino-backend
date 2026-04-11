<?php

namespace App\Http\Controllers;

use App\Models\Disciplineleague;

use App\Http\Controllers\Controller;

class DisciplineConfigController extends Controller
{

    public function index()
    {
        $disciplines = Disciplineleague::select('id', 'nom')
            ->get();
        return response()->json($disciplines);
    }
}
