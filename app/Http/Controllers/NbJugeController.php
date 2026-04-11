<?php

namespace App\Http\Controllers;

use App\Models\JugeOption;
use Illuminate\Http\Request;

class NbJugeController extends Controller
{
    public function index()
    {
        $juges = JugeOption::select('id', 'valeur', 'libelle')->get();

        return response()->json($juges);
    }
}
