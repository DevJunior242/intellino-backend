<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NiveauxCompetition;

class NiveauxCompetitionController extends Controller
{
    public function index()
    {
        $niveaux = NiveauxCompetition::select('id', 'nom')->get();
        return response()->json($niveaux);
    }
}
