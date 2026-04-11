<?php

namespace App\Http\Controllers;

use App\Models\Federation;
use Illuminate\Http\Request;

class FederationController extends Controller
{
    public function index()
    {
        $federations = Federation::select('id', 'nom_fede', 'logo')->get();
        return response()->json($federations);
    }
}
