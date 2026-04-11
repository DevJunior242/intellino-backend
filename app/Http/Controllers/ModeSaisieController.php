<?php

namespace App\Http\Controllers;

use App\Models\ModeSaisie;

class ModeSaisieController extends Controller
{
    public function index()
    {
        $modes = ModeSaisie::select('id', 'code', 'libelle', 'description')->get();

        return response()->json($modes);
    }
}
